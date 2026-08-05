# Twilio Voice Channel — Implementation Plan

Give every project its own phone number. When someone calls it, the AI answers, retrieves from
that project's indexed documents, and speaks the answer back. Each call is registered as a normal
chat conversation, with the caller's phone number recorded.

**Decisions taken:** TwiML `<Gather input="speech">` + `<Say>` for STT/TTS (no WebSocket server).
Numbers are bought in the Twilio console and assigned to a project in the admin UI, which validates
them against the Twilio API and configures their webhooks.

---

## 1. Architecture

The voice channel is a second front door onto the RAG pipeline that already exists. It reuses
`ChatAnswerService`, `ContextRetrievalService`, and the Ollama services untouched in principle —
only the prompt and the output formatting differ.

```
Caller dials +383 44 xxx xxx
        │
        ▼
     Twilio ──POST /api/twilio/voice/incoming──▶ TwilioVoiceController::incoming
        │                                          resolve Project by `To` number
        │                                          create ChatSession(channel='voice')
        │                                          create PhoneCall(from_number, call_sid)
        │◀────── TwiML: <Gather><Say>greeting</Say></Gather>
        │
   caller speaks ──▶ Twilio STT
        │
        ▼
     Twilio ──POST /api/twilio/voice/turn (SpeechResult="...")──▶ ::turn
        │                                          store user ChatMessage
        │                                          dispatch AnswerVoiceTurnJob
        │◀────── TwiML: <Say>one moment</Say><Redirect>/turn/wait</Redirect>
        │
        │◀──────▶ /turn/wait polls cache until the job lands
        │          (<Pause><Redirect> loop, ~2s per iteration)
        │
        │◀────── TwiML: <Gather><Say>answer</Say></Gather>   ← barge-in enabled
        ▼
   caller hears the answer and can interrupt with the next question
```

Two properties fall out of this design for free:

- `ChatAnswerService` already fires `ProjectChatMessageCreated`, which broadcasts over Reverb. Voice
  conversations will therefore stream live into the existing dashboard chat page with no extra work.
- Because voice sessions are ordinary `chat_sessions` rows with `channel='voice'`, the existing
  history endpoint (`GET /api/projects/{project}/chats/{chat}/history`) already renders call
  transcripts.

### The one hard constraint

Twilio abandons a webhook after **15 seconds**. Today `ChatAnswerService::answer()` makes *two*
sequential Ollama `/api/generate` calls (draft, then a normalising rewrite) at
[ChatAnswerService.php:48-62](../app/Services/Rag/ChatAnswerService.php#L48-L62). On local hardware
that is comfortably 10–40s — it would drop calls outright.

Two mitigations, both in Phase 4:

1. **Single-pass generation for voice.** The normalising second pass exists to clean up prose for a
   chat bubble; a voice-tuned system prompt produces speakable output directly. Halves latency.
2. **Async turn with a hold loop.** The turn webhook dispatches a queued job and returns immediately;
   a `<Redirect>` loop keeps the call alive while the job runs. This makes the call immune to Ollama
   latency entirely, which is why it is worth building from the start rather than retrofitting.

A related capacity note: Ollama serialises requests unless `OLLAMA_NUM_PARALLEL` is raised, so N
concurrent calls queue behind each other regardless of how many queue workers run. Size this before
going live.

---

## 2. Phase 0 — Prerequisites

| Item | Detail |
| --- | --- |
| Twilio account | Buy a voice-capable number per project. Trial accounts can only call verified numbers. |
| Composer dep | `composer require twilio/sdk` — gives `RequestValidator` for signature checks and the REST client for number validation and webhook configuration. Hand-rolling the HMAC is possible but not worth it since we need the REST API anyway. |
| Local tunnel | Twilio must reach the app from the public internet. `ngrok http 81` or `cloudflared tunnel`; the current `APP_URL=http://zetes-chat-widget.test:81` is unreachable from Twilio. |
| Queue worker | Already `QUEUE_CONNECTION=database`. Voice jobs go on a dedicated `voice` queue so document ingestion cannot block a live call. |

### New env vars

```dotenv
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_WEBHOOK_BASE_URL=https://your-tunnel.ngrok-free.app   # exact public origin Twilio calls
TWILIO_VALIDATE_SIGNATURE=true                                # false only for local curl testing

VOICE_QUEUE=voice
VOICE_DEFAULT_TTS_VOICE=Polly.Joanna
VOICE_DEFAULT_LANGUAGE=en-US
VOICE_MAX_TURNS=20
VOICE_MAX_ANSWER_CHARS=1200
VOICE_ANSWER_TIMEOUT_SECONDS=45
VOICE_HOLD_POLL_SECONDS=2
VOICE_ASYNC_ANSWER=true
```

Credentials go in `config/services.php` (Laravel convention for third-party APIs); behavioural
tuning goes in `config/rag.php` under a new `voice` key, next to the existing `widget` block.

---

## 3. Phase 1 — Data model

### Migration: `add_phone_to_projects_table`

```php
$table->string('phone_number')->nullable()->unique();   // E.164, e.g. +38344123456
$table->string('twilio_phone_sid')->nullable();
$table->json('voice_settings')->nullable();
```

The unique index on `phone_number` is what makes inbound routing safe — it makes it impossible for
two projects to claim the same number and silently cross-wire tenants.

### Migration: `create_phone_calls_table`

```php
$table->id();
$table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
$table->foreignId('project_id')->constrained()->cascadeOnDelete();
$table->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
$table->string('call_sid')->unique();
$table->string('from_number');            // ← the caller number to register
$table->string('to_number');
$table->string('from_country', 2)->nullable();
$table->string('from_city')->nullable();
$table->string('status')->default('ringing');   // ringing|in-progress|completed|failed|no-answer|busy
$table->string('direction')->default('inbound');
$table->unsignedInteger('turn_count')->default(0);
$table->unsignedInteger('duration_seconds')->nullable();
$table->string('recording_url')->nullable();
$table->timestamp('started_at')->nullable();
$table->timestamp('ended_at')->nullable();
$table->json('metadata')->nullable();
$table->timestamps();
$table->index(['tenant_id', 'project_id']);
$table->index(['project_id', 'from_number']);   // powers the caller directory
```

### Models

- **`app/Models/PhoneCall.php`** — `belongsTo` tenant / project / chatSession, `metadata` cast to
  array, following the `$fillable` + `casts()` convention in [ChatSession.php](../app/Models/ChatSession.php).
- **`Project`** — add `phone_number`, `twilio_phone_sid`, `voice_settings` to `$fillable`, cast
  `voice_settings` to array, add `phoneCalls(): HasMany`.

The caller number is written in **two** places on purpose: `phone_calls.from_number` is the
queryable record for the caller directory and analytics, and a copy in `chat_sessions.metadata`
(`caller.number`, `caller.country`, `call_sid`) lets the existing chat UI label a conversation
without a join.

### Voice settings defaults

Mirror the `ProjectController::defaultWidgetSettings()` / `serializeWidgetSettings()` static-pair
pattern with `defaultVoiceSettings()` / `serializeVoiceSettings()`:

```php
'enabled'            => false,
'greeting'           => 'Hello, thanks for calling. How can I help you today?',
'tts_voice'          => 'Polly.Joanna',
'language'           => 'en-US',        // BCP-47, not the 2-letter widget code
'speech_timeout'     => 'auto',
'max_turns'          => 20,
'no_input_prompt'    => "Sorry, I didn't catch that. Could you repeat your question?",
'fallback_message'   => "I'm having trouble right now. Please try again later.",
'goodbye_message'    => 'Thanks for calling. Goodbye.',
'record_calls'       => false,
'transfer_number'    => null,           // escalation target after repeated failures
```

Note `widget_settings.language` is a 2-letter code while Twilio needs BCP-47 (`en-US`), so voice
carries its own language field rather than deriving it.

---

## 4. Phase 2 — Twilio integration service

**`app/Services/Voice/TwilioNumberService.php`**

- `validateOwnership(string $e164): ?IncomingPhoneNumberInstance` — confirms the number exists in
  *your* Twilio account before it can be attached to a project.
- `configureWebhooks(string $sid, string $baseUrl): void` — points `voiceUrl` at
  `/api/twilio/voice/incoming`, `statusCallback` at `/api/twilio/voice/status`, and `voiceFallbackUrl`
  at `/api/twilio/voice/fallback`, all `POST`.
- `normalize(string $raw): string` — strips spaces/dashes, enforces leading `+`, validates E.164.

**`app/Http/Middleware/ValidateTwilioRequest.php`** (alias `twilio.webhook`, registered in
[bootstrap/app.php](../bootstrap/app.php) next to the existing `widget.request` alias)

Validates `X-Twilio-Signature` via the SDK's `RequestValidator`. The signature is computed over the
**exact URL Twilio requested**, so the middleware must build it from `TWILIO_WEBHOOK_BASE_URL` +
path rather than from `$request->fullUrl()` — behind ngrok or a reverse proxy the host and scheme
Laravel sees differ from what Twilio signed, and this is the single most common cause of
"every webhook returns 403" during setup. Honours `TWILIO_VALIDATE_SIGNATURE=false` for local
`curl` testing only, and logs loudly when disabled.

---

## 5. Phase 3 — Voice webhook controller

**`app/Http/Controllers/TwilioVoiceController.php`**, routed in [routes/api.php](../routes/api.php)
outside the `auth:sanctum` group:

```php
Route::middleware(['twilio.webhook', 'throttle:twilio-voice'])->prefix('twilio/voice')->group(function (): void {
    Route::post('/incoming',  [TwilioVoiceController::class, 'incoming']);
    Route::post('/turn',      [TwilioVoiceController::class, 'turn']);
    Route::post('/turn/wait', [TwilioVoiceController::class, 'wait']);
    Route::post('/status',    [TwilioVoiceController::class, 'status']);
    Route::post('/recording', [TwilioVoiceController::class, 'recording']);
    Route::post('/fallback',  [TwilioVoiceController::class, 'fallback']);
});
```

Add a `twilio-voice` rate limiter keyed on `From` alongside the existing `widget-chat-*` limiters.

### `incoming`

1. Resolve project: `Project::where('phone_number', $normalizedTo)->where('status', 'active')->first()`.
2. If no project, voice is disabled, or the project is inactive → `<Say>` a polite unavailable
   message and `<Hangup/>`. Never leak which numbers exist.
3. Create `ChatSession` — `channel='voice'`, title `"Call from {from_number}"`, metadata carrying
   `caller.number`, `caller.country`, `caller.city`, `call_sid`, `voice_session_started_at`.
4. Create `PhoneCall` with `status='in-progress'`, `started_at=now()`.
5. Return TwiML.

### TwiML shape (the details that matter)

```xml
<Response>
  <Gather input="speech"
          action="/api/twilio/voice/turn?call=..."
          method="POST"
          speechTimeout="auto"
          language="en-US"
          hints="invoice, onboarding, Confluence, ..."
          actionOnEmptyResult="true">
    <Say voice="Polly.Joanna">Hello, thanks for calling…</Say>
  </Gather>
  <Redirect>/api/twilio/voice/turn?call=...&amp;timeout=1</Redirect>
</Response>
```

- `<Say>` **nested inside** `<Gather>` is what enables barge-in — the caller can interrupt the answer
  with their next question instead of waiting it out. Nesting is not cosmetic; it is the feature.
- `actionOnEmptyResult="true"` guarantees the action fires even on silence, so no-input is handled
  by our code rather than by Twilio hanging up.
- `hints` materially improves STT on domain vocabulary. Derive it from the project's
  `suggested_questions` plus indexed document names — a cheap, high-leverage win.
- The trailing `<Redirect>` catches the case where `<Gather>` falls through.

### `turn`

1. Load `PhoneCall` by `CallSid`; increment `turn_count`.
2. If `turn_count > max_turns` → goodbye + `<Hangup/>` (protects against runaway loops and bill shock).
3. `SpeechResult` empty or `Confidence` below threshold → reprompt with `no_input_prompt`; on the
   second consecutive failure, transfer to `transfer_number` if set, otherwise say goodbye and hang up.
4. Store the user `ChatMessage` with `metadata.channel='voice'`, plus the raw `SpeechResult` and
   `Confidence` for later STT-quality review.
5. Dispatch `AnswerVoiceTurnJob` (cache key `voice:answer:{CallSid}:{turn}`); return
   `<Say>filler</Say><Redirect>/turn/wait</Redirect>`.

### `wait`

Reads the cache key. Answer ready → `<Gather>` with the answer nested inside. Not ready → short
`<Pause>` plus `<Redirect>` back to itself. Bounded by `VOICE_ANSWER_TIMEOUT_SECONDS`, after which
it speaks `fallback_message`. Each iteration is a fresh sub-15s webhook, so total answer time is
unbounded while every individual request stays well inside Twilio's limit.

### `status` / `recording` / `fallback`

`status` finalises the `PhoneCall` on `completed`/`failed`/`no-answer` — `ended_at`,
`duration_seconds` from `CallDuration`, final status — and appends a closing system message to the
session so the transcript reads as a complete conversation. `recording` stores `RecordingUrl` when
`record_calls` is on. `fallback` is Twilio's safety net when the primary handler errors: it speaks
`fallback_message` and logs at error level.

---

## 6. Phase 4 — Voice-tuned answering

### `ChatAnswerService` — additive change

Add an optional final parameter so all existing call sites keep working:

```php
public function answer(
    Project $project,
    ChatSession $chatSession,
    string $question,
    ?int $selectedDocumentId = null,
    ?AnswerOptions $options = null,   // new
): array
```

`AnswerOptions` (a small readonly class) carries `channel`, `singlePass`, `maxChars`, and
`promptStyle`. When `channel === 'voice'`: skip the normalising second generation, and swap in a
voice system prompt — answer in 2–3 spoken sentences, no markdown, no bullet lists, no URLs, spell
out numbers, and when context is insufficient say so plainly and offer to take a message.

### `app/Services/Voice/VoiceResponseFormatter.php`

Ollama output is not safe to hand to `<Say>` as-is. This service:

- strips markdown (`**`, `##`, backticks, fenced code, list markers);
- replaces URLs and file paths with speakable phrases ("the link is on our website");
- escapes XML entities (`&`, `<`, `>`, quotes) — an unescaped `&` in an answer breaks the whole TwiML
  document and drops the call;
- truncates at a sentence boundary to `VOICE_MAX_ANSWER_CHARS` (a single `<Say>` caps at 4096 chars,
  and anything near that is far too long to listen to anyway);
- expands common abbreviations that read badly aloud.

### `app/Jobs/AnswerVoiceTurnJob.php`

Follows the existing job convention in [app/Jobs/](../app/Jobs/) — IDs only in the constructor,
models re-fetched in `handle()`. Queue `voice`, `tries=2`, `timeout` slightly above the Ollama
timeout. Calls `ChatAnswerService` then `VoiceResponseFormatter`, and writes the result to cache for
the `wait` endpoint. `failed()` writes `fallback_message` to the same key so the call degrades
gracefully instead of hanging.

Citations are persisted exactly as they are today (the `message_citations` rows still get written),
they are simply not spoken — so per-document analytics keeps working across both channels.

---

## 7. Phase 5 — Admin API

Inside the `auth:sanctum` group, guarded by `AccessControlService` like every other project route:

```php
Route::get   ('/projects/{project}/voice-settings', [ProjectVoiceController::class, 'show']);
Route::put   ('/projects/{project}/voice-settings', [ProjectVoiceController::class, 'update']);
Route::post  ('/projects/{project}/phone-number',   [ProjectVoiceController::class, 'assignNumber']);
Route::delete('/projects/{project}/phone-number',   [ProjectVoiceController::class, 'releaseNumber']);
Route::get   ('/projects/{project}/calls',          [ProjectVoiceController::class, 'calls']);
Route::get   ('/projects/{project}/callers',        [ProjectVoiceController::class, 'callers']);
```

`assignNumber` normalises to E.164, calls `TwilioNumberService::validateOwnership()`, rejects a
number already bound to another project (422), stores `phone_number` + `twilio_phone_sid`, then
configures the webhooks. `releaseNumber` clears the webhooks and the columns but does **not** release
the number at Twilio — dropping a paid number from a UI click is not a side effect anyone wants.

`callers` is the caller directory: `from_number` grouped with call count, first/last call timestamp,
total minutes — the aggregate answer to "register the phone number that called us".

`calls` is paginated (unlike the existing unpaginated project endpoints) because call volume grows
without an upper bound.

---

## 8. Phase 6 — Admin UI

A **Phone** tab in [project-layout.component.ts](../frontend/src/app/layouts/project-layout.component.ts)
next to Widget, plus `projects/:projectId/phone` in
[app.routes.ts](../frontend/src/app/app.routes.ts), following the shape of
`project-widget-page.component.ts`.

**`frontend/src/app/pages/project-phone-page.component.ts`** — three cards:

1. **Number** — E.164 input, Validate & Assign, then a status panel showing the number, Twilio SID,
   webhook health, and a Remove action.
2. **Voice settings** — enabled toggle, greeting textarea, TTS voice picker, language select, speech
   timeout, max turns, no-input/fallback/goodbye messages, record-calls toggle, transfer number.
   Same save/dirty pattern as the widget settings form.
3. **Recent calls** — table of caller number, country, started at, duration, turns, status, with a
   row action linking to the transcript at `projects/:id/chat?session=:chatSessionId`. Plus a
   collapsible callers-directory view.

Also: add a channel badge (`widget` / `voice` / `dashboard`) and, for voice sessions, the caller
number to the session list in `project-chat-page.component.ts`, and extend
[rag-api.service.ts](../frontend/src/app/core/rag-api.service.ts) with the six new endpoints.

---

## 9. Phase 7 — Analytics

Extend [ProjectAnalyticsController](../app/Http/Controllers/ProjectAnalyticsController.php) with a
voice block: `total_calls`, `average_call_duration_seconds`, `average_turns_per_call`,
`completion_rate`, `top_calling_numbers`, `calls_by_day`, and a `widget` vs `voice` vs `dashboard`
split of the existing metrics. Add an optional `?channel=` filter so existing figures can be viewed
per channel.

One thing to fix while in here: that controller currently loads **every** session, message, and
citation for a project into memory (`->get()` with no limit,
[ProjectAnalyticsController.php:21-36](../app/Http/Controllers/ProjectAnalyticsController.php#L21-L36))
and aggregates in PHP. It works at today's volumes; voice transcripts are chatty and will make it a
problem. Moving the aggregates to SQL is a small, well-scoped piece of work that belongs with this
phase rather than after the first slow page.

---

## 10. Phase 8 — Tests

**`tests/Feature/TwilioVoiceCallTest.php`** — with `ChatAnswerService` faked and `Http::fake()` for
Ollama, in the style of [ProjectChatTest.php](../tests/Feature/ProjectChatTest.php):

- inbound call creates a `ChatSession(channel='voice')` and a `PhoneCall` with the caller number;
- TwiML asserts: `<Gather input="speech">` present, `<Say>` nested inside it, correct action URLs;
- a turn stores the user message, dispatches the job, and returns the hold redirect;
- the wait endpoint loops while the cache is empty and speaks the answer once populated;
- empty `SpeechResult` reprompts; two consecutive failures end or transfer the call;
- `turn_count > max_turns` hangs up;
- status callback finalises duration and status;
- unknown / disabled / inactive number → polite hangup, no session created, no enumeration leak;
- bad `X-Twilio-Signature` → 403;
- **tenant isolation**: a call to project A's number can never retrieve project B's chunks.

**`tests/Unit/VoiceResponseFormatterTest.php`** — markdown stripping, XML escaping (especially bare
`&`), sentence-boundary truncation, URL replacement.

Note that `phpunit.xml` uses in-memory SQLite, so tests exercise the non-pgvector retrieval branch —
already handled at [ContextRetrievalService.php:45-51](../app/Services/Rag/ContextRetrievalService.php#L45-L51).

---

## 11. Sequencing and effort

| Phase | Scope | Est. |
| --- | --- | --- |
| 0 | Twilio setup, SDK, env, tunnel | 0.5 d |
| 1 | Migrations, `PhoneCall` model, `Project` changes, voice-settings defaults | 1 d |
| 2 | `TwilioNumberService`, signature middleware | 1 d |
| 3 | `TwilioVoiceController`, TwiML, routes, rate limiter | 2 d |
| 4 | `AnswerOptions`, voice prompt, `VoiceResponseFormatter`, `AnswerVoiceTurnJob` | 2 d |
| 5 | `ProjectVoiceController` + admin routes | 1 d |
| 6 | Angular Phone tab, chat-page badges, API service | 2 d |
| 7 | Analytics voice block + SQL aggregate cleanup | 1.5 d |
| 8 | Feature + unit tests | 1.5 d |
|  | **Total** | **~12.5 d** |

The earliest end-to-end call happens after Phase 4 — that is the checkpoint worth aiming at, since
hearing a real call answered will surface prompt and latency issues that no amount of planning will.

Per project convention, run `graphify update .` after the code lands, and `composer test` before
each phase is called done.

---

## 11b. Measured latency (2026-08-03, this hardware)

The first live call failed exactly where the plan predicted. Numbers from the real pipeline:

| model | prompt tokens | wall time |
| --- | --- | --- |
| llama3 (8B) | 2013 | **284 s** |
| llama3 (8B) | 693 | 135 s |
| gemma:2b | 2028 | 87 s |
| gemma:2b | 708 | **8.4 s** |

Prompt *processing* dominates — 270 s of that 284 s — so the retrieval budget is a bigger lever
than the model choice, and the two multiply. Measured generation throughput for `gemma:2b` here is
~31 prompt tok/s and ~8 output tok/s.

Settings that resulted (`config/rag.php` → `rag.voice`): `gemma:2b`, `retrieval_top_k` 2,
`max_context_chars_per_chunk` 450, `num_predict` 80. End-to-end turns then measured **4.5–12.8 s**
across five consecutive runs, against a 45 s hold budget.

**The timeout ladder must stay ordered**, shortest first — this is what actually broke the first
call, where a 120 s Ollama timeout sat outside a 45 s hold budget:

```
ollama_timeout_seconds (35s)  <  answer_timeout_seconds (45s)  <  job timeout (60s)
```

`tests/Unit/AnswerOptionsTest.php` asserts that ordering so it cannot silently invert again.

### Two operational requirements

- **Warm the models before taking calls**: `php artisan voice:warm`. A cold model load exceeds any
  single turn's budget, so the first caller after an Ollama or machine restart would otherwise hear
  the fallback message. `rag.voice.model_keep_alive` (default `-1`) then pins them resident.
- **`keep_alive` is type-sensitive**: Ollama parses a string as a Go duration, so `"-1"` is rejected
  with `missing unit in duration` and *every* request 400s. The number `-1` means "never unload".
  Normalised in config; guarded by `tests/Unit/OllamaKeepAliveTest.php`.

Pinning the voice models holds ~2.2 GB of VRAM. The widget and dashboard channels still use the
larger `OLLAMA_GENERATION_MODEL`, so on a constrained GPU the two will contend — watch
`curl localhost:11434/api/ps` if widget answers slow down after enabling voice.

## 12. Risks

| Risk | Mitigation |
| --- | --- |
| **Ollama latency vs Twilio's 15s webhook timeout** — the primary technical risk | Single-pass voice generation + async hold loop (Phase 4). Measure p95 turn latency before launch. |
| **Concurrent calls serialise on Ollama** regardless of worker count | Raise `OLLAMA_NUM_PARALLEL`; load-test the target concurrent-call count; consider a dedicated generation host. |
| **STT mishears domain vocabulary** | `hints` from document names and suggested questions; store `Confidence` per turn and review the low-confidence tail. |
| **Signature validation fails behind the tunnel** | Build the signed URL from `TWILIO_WEBHOOK_BASE_URL`, never from `$request->fullUrl()`. |
| **PII: caller numbers and recordings** | Recording defaults off. Define a retention window and a prune command. Projects already carry `country_code`, so EU calls bring GDPR obligations — get a lawful basis and a call-recording notice in the greeting before enabling recording. |
| **Cost runaway** — per-minute voice, STT, and TTS all bill per use | `max_turns` cap, per-`From` rate limiting, absolute call-duration cap, and a cost panel in analytics. |
| **RAG says "context is insufficient" on a phone call** | Voice prompt offers to take a message or transfer to `transfer_number` rather than dead-ending the caller. |

---

## 13. Open items for later

- **Outbound calls / callbacks** — the schema carries `direction` so this is additive, but it is out
  of scope here.
- **Multilingual calls** — Twilio STT needs the language declared up front, so mid-call language
  switching would need a language-detection turn. Deferred.
- **SMS on the same number** — a natural follow-on: the same `ChatAnswerService` path with
  `channel='sms'`, reusing this entire data model.
- **Voicemail** — when the AI cannot answer, record a message and attach it to the session.

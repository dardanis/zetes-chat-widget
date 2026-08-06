<?php

namespace App\Console\Commands;

use App\Models\ChatSession;
use App\Models\PhoneCall;
use App\Models\Project;
use App\Services\Voice\TwilioNumberService;
use App\Services\Voice\VoiceSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * End-to-end configuration check for the voice channel.
 *
 * Most voice failures are configuration, not code, and they fail silently: if Twilio cannot reach
 * the callback URL the caller hears "an application error has occurred" and nothing is ever
 * logged, because the request never arrives. This renders the exact TwiML the server would emit
 * so the callback URL is visible rather than inferred.
 */
class VoiceDoctorCommand extends Command
{
    protected $signature = 'voice:doctor {--project= : Project id to inspect (defaults to the first with a number)}';

    protected $description = 'Diagnose the Twilio voice channel configuration';

    private int $problems = 0;

    public function handle(TwilioNumberService $numberService): int
    {
        $this->line('');
        $this->components->info('Voice channel diagnostics');

        $this->checkConfigCache();
        $this->checkAppEnv();
        $baseUrl = $this->checkWebhookBaseUrl();
        $this->checkSignatureValidation();
        $project = $this->checkProject();
        $this->checkOllama();

        if ($project) {
            $this->checkTwilioNumber($numberService, $project);
            $this->renderGreetingTwiml($project, $baseUrl);
        }

        $this->line('');

        if ($this->problems > 0) {
            $this->components->error("{$this->problems} problem(s) found. Fix the FAIL lines above, then run: php artisan config:clear");

            return self::FAILURE;
        }

        $this->components->info('No configuration problems found.');

        return self::SUCCESS;
    }

    private function checkConfigCache(): void
    {
        if (file_exists($this->laravel->getCachedConfigPath())) {
            $this->warn('  WARN  Config is CACHED. Edits to .env are ignored until you run: php artisan config:clear');
        } else {
            $this->ok('Config is not cached; .env is live.');
        }
    }

    private function checkAppEnv(): void
    {
        $env = (string) config('app.env');

        if ($env !== 'production') {
            $this->warn("  WARN  APP_ENV is [{$env}]. Production servers should run APP_ENV=production with APP_DEBUG=false.");
        } else {
            $this->ok('APP_ENV is production.');
        }
    }

    private function checkWebhookBaseUrl(): string
    {
        $base = rtrim((string) config('services.twilio.webhook_base_url'), '/');

        if ($base === '') {
            $this->problem('TWILIO_WEBHOOK_BASE_URL is EMPTY. Twilio will be told to call back on a URL derived from the request, which may not be correct behind a proxy.');

            return $base;
        }

        $host = parse_url($base, PHP_URL_HOST) ?: $base;
        $scheme = parse_url($base, PHP_URL_SCHEME);

        if (preg_match('/^(localhost|127\.\d+\.\d+\.\d+|0\.0\.0\.0|\[?::1\]?|.+\.(test|local|localhost|internal))$/i', $host)) {
            $this->problem("TWILIO_WEBHOOK_BASE_URL is [{$base}] - Twilio CANNOT reach this host from the internet. This is the usual cause of \"an application error has occurred\" with no logs.");

            return $base;
        }

        if ($scheme !== 'https') {
            $this->warn("  WARN  TWILIO_WEBHOOK_BASE_URL [{$base}] is not https.");
        }

        $this->ok("TWILIO_WEBHOOK_BASE_URL is [{$base}].");
        $this->probeSelf($base.'/api/twilio/voice/incoming');

        return $base;
    }

    /**
     * Prove the callback URL is reachable from outside, the way Twilio would reach it.
     */
    private function probeSelf(string $url): void
    {
        try {
            $response = Http::timeout(10)->asForm()->post($url, [
                'CallSid' => 'CAdoctor'.uniqid(),
                'From' => '+10000000000',
                'To' => '+10000000001',
            ]);

            if ($response->successful()) {
                $this->ok("Callback URL answered {$response->status()} from outside.");
            } else {
                $this->problem("Callback URL returned HTTP {$response->status()}. Twilio needs a 2xx with TwiML.");
            }
        } catch (Throwable $e) {
            $this->problem('Could not reach the callback URL from this server: '.$e->getMessage());
        }
    }

    private function checkSignatureValidation(): void
    {
        if (! config('services.twilio.validate_signature')) {
            $this->warn('  WARN  TWILIO_VALIDATE_SIGNATURE is false. Webhooks are unauthenticated (this does NOT break calls).');
        } elseif ((string) config('services.twilio.auth_token') === '') {
            $this->problem('Signature validation is on but TWILIO_AUTH_TOKEN is empty; every webhook will 500.');
        } else {
            $this->ok('Signature validation is enabled.');
        }
    }

    private function checkProject(): ?Project
    {
        $id = $this->option('project');

        $project = $id
            ? Project::query()->find((int) $id)
            : Project::query()->whereNotNull('phone_number')->first();

        if (! $project) {
            $this->problem('No project with a phone number assigned. Assign one in the Phone tab.');

            return null;
        }

        $settings = VoiceSettings::for($project);

        $this->ok("Project [{$project->name}] #{$project->id} -> {$project->phone_number}");

        if ($project->status !== 'active') {
            $this->problem("Project status is [{$project->status}]; inbound calls are rejected unless it is 'active'.");
        }

        if (! ($settings['enabled'] ?? false)) {
            $this->problem('Voice channel is DISABLED for this project. Tick "Answer incoming calls" in the Phone tab.');
        } else {
            $this->ok('Voice channel is enabled.');
        }

        $indexed = $project->documents()->where('status', 'indexed')->count();

        if ($indexed === 0) {
            $this->warn('  WARN  No indexed documents; answers will always say context is insufficient.');
        } else {
            $this->ok("{$indexed} indexed document(s) available for retrieval.");
        }

        return $project;
    }

    private function checkOllama(): void
    {
        $base = rtrim((string) config('rag.ollama.base_url'), '/');
        $wanted = [
            (string) config('rag.voice.generation_model'),
            (string) config('rag.ollama.embedding_model'),
        ];

        try {
            $response = Http::timeout(10)->get($base.'/api/tags');
        } catch (Throwable $e) {
            $this->problem("Ollama unreachable at {$base}: ".$e->getMessage());

            return;
        }

        if (! $response->successful()) {
            $this->problem("Ollama at {$base} returned HTTP {$response->status()}.");

            return;
        }

        $available = collect($response->json('models') ?? [])->pluck('name')->all();
        $this->ok("Ollama reachable at {$base}.");

        foreach (array_filter($wanted) as $model) {
            $present = collect($available)->contains(
                fn (string $name): bool => $name === $model || str_starts_with($name, $model.':')
            );

            $present
                ? $this->ok("Model [{$model}] is pulled.")
                : $this->problem("Model [{$model}] is NOT pulled. Run: ollama pull {$model}");
        }

        // The generation timeout must fit inside the caller's hold budget.
        $generation = (int) config('rag.voice.ollama_timeout_seconds');
        $hold = (int) config('rag.voice.answer_timeout_seconds');

        $generation < $hold
            ? $this->ok("Timeout ladder ok: generation {$generation}s < hold budget {$hold}s.")
            : $this->problem("Generation timeout ({$generation}s) must be BELOW the hold budget ({$hold}s).");
    }

    private function checkTwilioNumber(TwilioNumberService $numberService, Project $project): void
    {
        if (! $numberService->isConfigured()) {
            $this->problem('Twilio credentials are not configured.');

            return;
        }

        try {
            $number = $numberService->findOwnedNumber((string) $project->phone_number);
            $this->ok("Number {$number['phone_number']} found in the Twilio account (SID {$number['sid']}).");
        } catch (Throwable $e) {
            $this->problem('Twilio number check failed: '.$e->getMessage());
        }
    }

    /**
     * The decisive output: the callback URL Twilio is actually handed.
     */
    private function renderGreetingTwiml(Project $project, string $baseUrl): void
    {
        $this->line('');
        $this->components->info('TwiML your server would return for an incoming call');

        $callSid = 'CAdoctor'.uniqid();

        try {
            $response = Http::timeout(15)->asForm()->post(
                ($baseUrl !== '' ? $baseUrl : (string) config('app.url')).'/api/twilio/voice/incoming',
                [
                    'CallSid' => $callSid,
                    'From' => '+10000000000',
                    'To' => (string) $project->phone_number,
                ]
            );

            $body = $response->body();
            $this->line('');
            $this->line($body);
            $this->line('');

            if (preg_match('/action="([^"]+)"/', $body, $m)) {
                $action = html_entity_decode($m[1]);
                $host = parse_url($action, PHP_URL_HOST);

                if (! $host) {
                    $this->problem("Callback URL [{$action}] has no host; Twilio cannot follow it.");
                } elseif (preg_match('/^(localhost|127\.|0\.0\.0\.0|::1)/i', $host) || preg_match('/\.(test|local|internal)$/i', $host)) {
                    $this->problem("Callback URL points at [{$host}], which Twilio cannot reach. THIS is why the call fails after the greeting.");
                } else {
                    $this->ok("Callback URL is [{$action}] - publicly routable.");
                }
            } else {
                $this->problem('No <Gather action="..."> in the response. The call would end after the greeting. Check the project is active and voice is enabled.');
            }
        } catch (Throwable $e) {
            $this->problem('Could not render TwiML: '.$e->getMessage());
        } finally {
            // The probe goes through the real webhook, which opens a session and a call record.
            // Remove them so diagnostics never show up in the customer's call log or analytics.
            $this->discardProbeArtifacts($callSid);
        }
    }

    private function discardProbeArtifacts(string $callSid): void
    {
        $call = PhoneCall::query()->where('call_sid', $callSid)->first();

        if (! $call) {
            return;
        }

        $session = ChatSession::query()->find($call->chat_session_id);

        $call->delete();
        $session?->messages()->delete();
        $session?->delete();
    }

    private function ok(string $message): void
    {
        $this->line("  <fg=green>OK</>    {$message}");
    }

    private function problem(string $message): void
    {
        $this->problems++;
        $this->line("  <fg=red>FAIL</>  {$message}");
    }
}

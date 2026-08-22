import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { PhoneCall, PhoneCaller, RagApiService, VoiceSettings } from '../core/rag-api.service';

@Component({
  selector: 'app-project-phone-page',
  standalone: true,
  imports: [FormsModule, RouterLink, DatePipe],
  template: `
    @if (isLoading()) {
      <div class="h-96 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
    } @else {
      <section class="space-y-6">
        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
          <div class="flex flex-col gap-1">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Phone number</h3>
            <p class="text-sm text-[var(--app-text-muted)]">Buy a voice-capable number in the Twilio console, then assign it here. We verify it belongs to your account and configure its webhooks automatically.</p>
          </div>

          @if (!settings.twilio_configured) {
            <div class="mt-4 rounded-lg border border-amber-300/50 bg-amber-50 px-3 py-2 text-sm text-amber-800">
              Twilio credentials are not configured. Set <code>TWILIO_ACCOUNT_SID</code> and <code>TWILIO_AUTH_TOKEN</code> in your environment.
            </div>
          }

          @if (settings.phone_number) {
            <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
              <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2.5">
                <p class="font-mono text-sm text-[var(--app-text)]">{{ settings.phone_number }}</p>
                <p class="mt-1 text-xs text-[var(--app-text-muted)]">Twilio SID {{ settings.twilio_phone_sid }}</p>
              </div>
              <button type="button" (click)="release()" [disabled]="isSaving()" class="rounded-lg border border-[var(--app-danger)]/40 px-4 py-2 text-sm font-medium text-[var(--app-danger)] transition hover:bg-[var(--app-danger)]/10 disabled:opacity-60">
                Remove
              </button>
            </div>
            <p class="mt-3 text-xs text-[var(--app-text-muted)]">Removing unbinds the webhooks but keeps the number in your Twilio account.</p>
          } @else {
            <div class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
              <input name="phoneNumber" [(ngModel)]="phoneNumberInput" placeholder="+38344123456" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 font-mono text-sm text-[var(--app-text)] outline-none placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              <button type="button" (click)="assign()" [disabled]="isSaving() || !phoneNumberInput.trim()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
                Validate &amp; assign
              </button>
            </div>
          }

          <div class="mt-4 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Voice webhook</p>
            <p class="mt-1 break-all font-mono text-xs text-[var(--app-text)]">{{ settings.webhook_url || 'Set TWILIO_WEBHOOK_BASE_URL' }}</p>
          </div>
        </div>

        <form class="space-y-6" (ngSubmit)="save()">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <div class="flex flex-col gap-1">
              <h3 class="text-sm font-semibold text-[var(--app-text)]">Voice settings</h3>
              <p class="text-sm text-[var(--app-text-muted)]">How the assistant behaves when someone calls this number.</p>
            </div>

            <label class="mt-5 flex items-center gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2">
              <input name="enabled" type="checkbox" [(ngModel)]="settings.enabled" class="h-4 w-4 rounded border-[var(--app-border)]" />
              <span class="text-sm text-[var(--app-text)]">Answer incoming calls with the AI assistant</span>
            </label>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <label class="space-y-1.5 md:col-span-2">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Greeting</span>
                <textarea name="greeting" [(ngModel)]="settings.greeting" rows="2" class="w-full resize-none rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40"></textarea>
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Voice</span>
                <select name="ttsVoice" [(ngModel)]="settings.tts_voice" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40">
                  @for (group of voiceOptions; track group.label) {
                    <optgroup [label]="group.label">
                      @for (voice of group.voices; track voice.value) {
                        <option [value]="voice.value">{{ voice.label }}</option>
                      }
                    </optgroup>
                  }
                </select>
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Language</span>
                <select name="language" [(ngModel)]="settings.language" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40">
                  @for (language of languageOptions; track language.code) {
                    <option [value]="language.code">{{ language.label }}</option>
                  }
                </select>
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Speech timeout</span>
                <input name="speechTimeout" [(ngModel)]="settings.speech_timeout" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
                <span class="text-[11px] text-[var(--app-text-muted)]">"auto" lets Twilio detect end of speech, or give a number of seconds.</span>
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Max turns per call</span>
                <input name="maxTurns" type="number" min="1" max="100" [(ngModel)]="settings.max_turns" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5 md:col-span-2">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Thinking message</span>
                <input name="thinkingMessage" [(ngModel)]="settings.thinking_message" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
                <span class="text-[11px] text-[var(--app-text-muted)]">Spoken while the answer is being generated.</span>
              </label>

              <label class="space-y-1.5 md:col-span-2">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">No input prompt</span>
                <input name="noInputPrompt" [(ngModel)]="settings.no_input_prompt" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5 md:col-span-2">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Fallback message</span>
                <input name="fallbackMessage" [(ngModel)]="settings.fallback_message" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Goodbye message</span>
                <input name="goodbyeMessage" [(ngModel)]="settings.goodbye_message" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Unavailable message</span>
                <input name="unavailableMessage" [(ngModel)]="settings.unavailable_message" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Transfer number (optional)</span>
                <input name="transferNumber" [(ngModel)]="settings.transfer_number" placeholder="+38344999888" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 font-mono text-sm text-[var(--app-text)] outline-none placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-[var(--app-accent)]/40" />
                <span class="text-[11px] text-[var(--app-text-muted)]">Where to send callers the assistant cannot help.</span>
              </label>

              <label class="flex items-center gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 md:col-span-2">
                <input name="recordCalls" type="checkbox" [(ngModel)]="settings.record_calls" class="h-4 w-4 rounded border-[var(--app-border)]" />
                <span class="text-sm text-[var(--app-text)]">Record calls</span>
              </label>
              <p class="text-xs text-[var(--app-text-muted)] md:col-span-2">Recording calls carries legal obligations in most countries. Announce it in the greeting and confirm you have a lawful basis before enabling this.</p>
            </div>
          </div>

          @if (error()) {
            <div class="rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ error() }}</div>
          }

          @if (success()) {
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ success() }}</div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="isSaving()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
              {{ isSaving() ? 'Saving...' : 'Save voice settings' }}
            </button>
          </div>
        </form>

        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Recent calls</h3>
            <button type="button" (click)="toggleCallers()" class="rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-xs font-medium text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">
              {{ showCallers() ? 'Show calls' : 'Show callers' }}
            </button>
          </div>

          @if (showCallers()) {
            @if (callers().length === 0) {
              <p class="mt-4 text-sm text-[var(--app-text-muted)]">No callers yet.</p>
            } @else {
              <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[520px] text-sm">
                  <thead>
                    <tr class="border-b border-[var(--app-border)] text-left text-xs text-[var(--app-text-muted)]">
                      <th class="pb-2 pr-4 font-medium">Number</th>
                      <th class="pb-2 pr-4 font-medium">Country</th>
                      <th class="pb-2 pr-4 font-medium">Calls</th>
                      <th class="pb-2 pr-4 font-medium">Total time</th>
                      <th class="pb-2 font-medium">Last call</th>
                    </tr>
                  </thead>
                  <tbody>
                    @for (caller of callers(); track caller.from_number) {
                      <tr class="border-b border-[var(--app-border)]/60">
                        <td class="py-2 pr-4 font-mono text-[var(--app-text)]">{{ caller.from_number }}</td>
                        <td class="py-2 pr-4 text-[var(--app-text-muted)]">{{ caller.from_country || '—' }}</td>
                        <td class="py-2 pr-4 text-[var(--app-text)]">{{ caller.call_count }}</td>
                        <td class="py-2 pr-4 text-[var(--app-text-muted)]">{{ formatDuration(caller.total_seconds) }}</td>
                        <td class="py-2 text-[var(--app-text-muted)]">{{ caller.last_call_at | date: 'short' }}</td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            }
          } @else {
            @if (calls().length === 0) {
              <p class="mt-4 text-sm text-[var(--app-text-muted)]">No calls yet. Assign a number, enable the voice channel, and dial in.</p>
            } @else {
              <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                  <thead>
                    <tr class="border-b border-[var(--app-border)] text-left text-xs text-[var(--app-text-muted)]">
                      <th class="pb-2 pr-4 font-medium">Caller</th>
                      <th class="pb-2 pr-4 font-medium">Started</th>
                      <th class="pb-2 pr-4 font-medium">Duration</th>
                      <th class="pb-2 pr-4 font-medium">Turns</th>
                      <th class="pb-2 pr-4 font-medium">Status</th>
                      <th class="pb-2 font-medium">Transcript</th>
                    </tr>
                  </thead>
                  <tbody>
                    @for (call of calls(); track call.id) {
                      <tr class="border-b border-[var(--app-border)]/60">
                        <td class="py-2 pr-4 font-mono text-[var(--app-text)]">{{ call.from_number }}</td>
                        <td class="py-2 pr-4 text-[var(--app-text-muted)]">{{ call.started_at | date: 'short' }}</td>
                        <td class="py-2 pr-4 text-[var(--app-text-muted)]">{{ formatDuration(call.duration_seconds) }}</td>
                        <td class="py-2 pr-4 text-[var(--app-text)]">{{ call.turn_count }}</td>
                        <td class="py-2 pr-4">
                          <span class="rounded-md border border-[var(--app-border)] px-2 py-0.5 text-xs text-[var(--app-text-muted)]">{{ call.status }}</span>
                        </td>
                        <td class="py-2">
                          @if (call.chat_session_id) {
                            <a [routerLink]="['../chat']" [queryParams]="{ session: call.chat_session_id }" class="text-xs font-medium text-[var(--app-accent)] hover:underline">View</a>
                          } @else {
                            <span class="text-xs text-[var(--app-text-muted)]">—</span>
                          }
                        </td>
                      </tr>
                    }
                  </tbody>
                </table>
              </div>
            }
          }
        </div>
      </section>
    }
  `,
})
export class ProjectPhonePageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly error = signal('');
  protected readonly success = signal('');
  protected readonly calls = signal<PhoneCall[]>([]);
  protected readonly callers = signal<PhoneCaller[]>([]);
  protected readonly showCallers = signal(false);

  protected settings: VoiceSettings = this.defaultSettings();
  protected phoneNumberInput = '';

  /*
   * Grouped by Twilio's TTS tier, because the tier is the only thing that decides whether a
   * caller hears a person or a robot -- switching between two voices in the same tier changes
   * who the robot is, not that it is one. Generative > Neural > Standard > Basic, and the
   * cheap tiers are kept only so existing projects that saved one still show their value.
   *
   * A voice carries its own language, so the Language field below is really only consulted for
   * the Basic voices; picking a UK voice with en-US selected is harmless, but picking an
   * English voice for a non-English language is not -- it reads the text with English phonemes.
   */
  protected readonly voiceOptions = [
    {
      label: 'Generative - most natural (beta, billed per character)',
      voices: [
        { value: 'Polly.Joanna-Generative', label: 'Joanna - US female' },
        { value: 'Polly.Danielle-Generative', label: 'Danielle - US female' },
        { value: 'Polly.Ruth-Generative', label: 'Ruth - US female' },
        { value: 'Polly.Matthew-Generative', label: 'Matthew - US male' },
        { value: 'Polly.Stephen-Generative', label: 'Stephen - US male' },
        { value: 'Polly.Amy-Generative', label: 'Amy - UK female' },
        { value: 'Google.en-US-Chirp3-HD-Aoede', label: 'Aoede - US female (Google)' },
        { value: 'Google.en-US-Chirp3-HD-Charon', label: 'Charon - US male (Google)' },
      ],
    },
    {
      label: 'Neural - natural, generally available',
      voices: [
        { value: 'Polly.Joanna-Neural', label: 'Joanna - US female' },
        { value: 'Polly.Kendra-Neural', label: 'Kendra - US female' },
        { value: 'Polly.Kimberly-Neural', label: 'Kimberly - US female' },
        { value: 'Polly.Matthew-Neural', label: 'Matthew - US male' },
        { value: 'Polly.Joey-Neural', label: 'Joey - US male' },
        { value: 'Polly.Amy-Neural', label: 'Amy - UK female' },
        { value: 'Polly.Emma-Neural', label: 'Emma - UK female' },
        { value: 'Polly.Brian-Neural', label: 'Brian - UK male' },
        { value: 'Google.en-US-Neural2-C', label: 'Neural2-C - US female (Google)' },
        { value: 'Google.en-US-Neural2-J', label: 'Neural2-J - US male (Google)' },
        { value: 'Google.en-GB-Neural2-N', label: 'Neural2-N - UK female (Google)' },
      ],
    },
    {
      label: 'Standard - robotic, cheapest',
      voices: [
        { value: 'Polly.Joanna', label: 'Joanna - US female' },
        { value: 'Polly.Matthew', label: 'Matthew - US male' },
        { value: 'Polly.Amy', label: 'Amy - UK female' },
        { value: 'Polly.Brian', label: 'Brian - UK male' },
        { value: 'Polly.Vicki', label: 'Vicki - German female' },
        { value: 'Polly.Lupe', label: 'Lupe - US Spanish female' },
      ],
    },
    {
      label: 'Basic - legacy, not recommended',
      voices: [
        { value: 'alice', label: 'alice' },
        { value: 'man', label: 'man' },
        { value: 'woman', label: 'woman' },
      ],
    },
  ];

  protected readonly languageOptions = [
    { code: 'en-US', label: 'English (US)' },
    { code: 'en-GB', label: 'English (UK)' },
    { code: 'de-DE', label: 'German' },
    { code: 'fr-FR', label: 'French' },
    { code: 'it-IT', label: 'Italian' },
    { code: 'es-ES', label: 'Spanish' },
    { code: 'nl-NL', label: 'Dutch' },
    { code: 'sq-AL', label: 'Albanian' },
    { code: 'tr-TR', label: 'Turkish' },
    { code: 'pl-PL', label: 'Polish' },
  ];

  ngOnInit(): void {
    this.load();
  }

  protected save(): void {
    this.error.set('');
    this.success.set('');
    this.isSaving.set(true);

    this.api.updateVoiceSettings(this.projectId(), {
      enabled: this.settings.enabled,
      greeting: this.settings.greeting,
      tts_voice: this.settings.tts_voice,
      language: this.settings.language,
      speech_timeout: String(this.settings.speech_timeout).trim(),
      max_turns: Number(this.settings.max_turns),
      thinking_message: this.settings.thinking_message,
      no_input_prompt: this.settings.no_input_prompt,
      fallback_message: this.settings.fallback_message,
      goodbye_message: this.settings.goodbye_message,
      unavailable_message: this.settings.unavailable_message,
      record_calls: this.settings.record_calls,
      transfer_number: this.settings.transfer_number?.trim() || null,
    }).subscribe({
      next: ({ data }) => {
        this.settings = { ...this.settings, ...data };
        this.success.set('Voice settings saved.');
      },
      error: (error: HttpErrorResponse) => {
        this.error.set(error.error?.message ?? 'Unable to save voice settings.');
        this.isSaving.set(false);
      },
      complete: () => this.isSaving.set(false),
    });
  }

  protected assign(): void {
    this.error.set('');
    this.success.set('');
    this.isSaving.set(true);

    this.api.assignPhoneNumber(this.projectId(), this.phoneNumberInput.trim()).subscribe({
      next: ({ data }) => {
        this.settings = { ...this.settings, ...data };
        this.phoneNumberInput = '';
        this.success.set('Number assigned and webhooks configured.');
      },
      error: (error: HttpErrorResponse) => {
        this.error.set(error.error?.message ?? 'Unable to assign that number.');
        this.isSaving.set(false);
      },
      complete: () => this.isSaving.set(false),
    });
  }

  protected release(): void {
    this.error.set('');
    this.success.set('');
    this.isSaving.set(true);

    this.api.releasePhoneNumber(this.projectId()).subscribe({
      next: ({ data }) => {
        this.settings = { ...this.settings, ...data };
        this.success.set('Number removed. It is still available in your Twilio account.');
      },
      error: (error: HttpErrorResponse) => {
        this.error.set(error.error?.message ?? 'Unable to remove that number.');
        this.isSaving.set(false);
      },
      complete: () => this.isSaving.set(false),
    });
  }

  protected toggleCallers(): void {
    this.showCallers.update((value) => !value);
  }

  protected formatDuration(seconds?: number | null): string {
    if (seconds === null || seconds === undefined) {
      return '—';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    return minutes > 0 ? `${minutes}m ${remainder}s` : `${remainder}s`;
  }

  private load(): void {
    const projectId = this.projectId();

    forkJoin([
      this.api.getVoiceSettings(projectId),
      this.api.listCalls(projectId, { per_page: 15 }),
      this.api.listCallers(projectId),
    ]).subscribe({
      next: ([settings, calls, callers]) => {
        this.settings = { ...this.defaultSettings(), ...settings.data };
        this.calls.set(calls.data);
        this.callers.set(callers.data);
      },
      complete: () => this.isLoading.set(false),
      error: () => {
        this.error.set('Unable to load phone settings.');
        this.isLoading.set(false);
      },
    });
  }

  private projectId(): number {
    return Number(this.route.parent?.snapshot.paramMap.get('projectId'));
  }

  private defaultSettings(): VoiceSettings {
    return {
      enabled: false,
      greeting: 'Hello, thanks for calling. How can I help you today?',
      tts_voice: 'Polly.Joanna-Neural',
      language: 'en-US',
      speech_timeout: 'auto',
      max_turns: 20,
      thinking_message: 'One moment while I look that up.',
      no_input_prompt: "Sorry, I didn't catch that. Could you repeat your question?",
      fallback_message: "I'm having trouble right now. Please try again later.",
      goodbye_message: 'Thanks for calling. Goodbye.',
      unavailable_message: 'Sorry, this number is not available right now. Goodbye.',
      record_calls: false,
      transfer_number: null,
      phone_number: null,
      twilio_phone_sid: null,
      twilio_configured: false,
      webhook_url: '',
    };
  }
}

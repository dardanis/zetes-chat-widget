import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { forkJoin } from 'rxjs';
import { Project, RagApiService, WidgetSettings } from '../core/rag-api.service';

@Component({
  selector: 'app-project-widget-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    @if (isLoading()) {
      <div class="h-96 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
    } @else {
      <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <form class="space-y-6" (ngSubmit)="save()">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <div class="flex flex-col gap-1">
              <h3 class="text-sm font-semibold text-[var(--app-text)]">Widget settings</h3>
              <p class="text-sm text-[var(--app-text-muted)]">Configure how this project chat widget appears on external websites.</p>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Title</span>
                <input name="title" [(ngModel)]="form.title" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Language</span>
                <input name="language" [(ngModel)]="form.language" maxlength="2" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5 md:col-span-2">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Welcome message</span>
                <textarea name="welcome" [(ngModel)]="form.welcome_message" rows="3" class="w-full resize-none rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40"></textarea>
              </label>

              <label class="space-y-1.5 md:col-span-2">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Input placeholder</span>
                <input name="placeholder" [(ngModel)]="form.input_placeholder" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Primary color</span>
                <div class="flex gap-2">
                  <input name="primaryColorPicker" type="color" [(ngModel)]="form.primary_color" class="h-10 w-12 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-1" />
                  <input name="primaryColor" [(ngModel)]="form.primary_color" class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40" />
                </div>
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Position</span>
                <select name="position" [(ngModel)]="form.position" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40">
                  <option value="bottom-right">Bottom right</option>
                  <option value="bottom-left">Bottom left</option>
                </select>
              </label>

              <label class="space-y-1.5">
                <span class="text-xs font-medium text-[var(--app-text-muted)]">Theme</span>
                <select name="theme" [(ngModel)]="form.theme" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none focus:ring-2 focus:ring-[var(--app-accent)]/40">
                  <option value="auto">Auto</option>
                  <option value="light">Light</option>
                  <option value="dark">Dark</option>
                </select>
              </label>

              <label class="flex items-center gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2">
                <input name="showCitations" type="checkbox" [(ngModel)]="form.show_citations" class="h-4 w-4 rounded border-[var(--app-border)]" />
                <span class="text-sm text-[var(--app-text)]">Show citations in widget answers</span>
              </label>
              <p class="text-xs text-[var(--app-text-muted)] md:col-span-2">Citations appear only when the answer has document sources. When disabled, the widget API returns answers without source blocks.</p>
            </div>
          </div>

          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Allowed domains</h3>
            <p class="mt-1 text-sm text-[var(--app-text-muted)]">Leave empty to allow the widget from any domain. When configured, widget chat requests are accepted only from these domains.</p>
            <textarea name="allowedDomains" [(ngModel)]="allowedDomainsText" rows="4" placeholder="example.com&#10;help.example.com" class="mt-4 w-full resize-none rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-[var(--app-accent)]/40"></textarea>
          </div>

          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Suggested questions</h3>
            <p class="mt-1 text-sm text-[var(--app-text-muted)]">One question per line. These appear before the first message.</p>
            <textarea name="suggestedQuestions" [(ngModel)]="suggestedQuestionsText" rows="4" placeholder="How do I get started?&#10;Where can I find support?" class="mt-4 w-full resize-none rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none placeholder:text-[var(--app-text-muted)] focus:ring-2 focus:ring-[var(--app-accent)]/40"></textarea>
          </div>

          @if (error()) {
            <div class="rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ error() }}</div>
          }

          @if (success()) {
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ success() }}</div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="isSaving()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
              {{ isSaving() ? 'Saving...' : 'Save widget settings' }}
            </button>
          </div>
        </form>

        <aside class="space-y-6">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Live preview</h3>
            <div class="mt-4 rounded-xl border border-[var(--app-border)] bg-[var(--app-bg)] p-4">
              <div class="overflow-hidden rounded-xl border border-[var(--app-border)]" [style.border-color]="form.primary_color">
                <div class="flex items-center justify-between px-4 py-3 text-white" [style.background]="form.primary_color">
                  <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-white/20 text-xs font-bold">Z</div>
                    <span class="text-sm font-semibold">{{ form.title }}</span>
                  </div>
                  <span class="text-lg leading-none">&times;</span>
                </div>
                <div class="space-y-3 bg-[var(--app-surface)] p-4">
                  <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3 text-sm text-[var(--app-text-muted)]">{{ form.welcome_message }}</div>
                  @for (question of suggestedQuestions(); track question) {
                    <button type="button" class="block w-full rounded-lg border border-[var(--app-border)] px-3 py-2 text-left text-xs text-[var(--app-text)]">{{ question }}</button>
                  }
                  <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3">
                    <p class="text-xs text-[var(--app-text)]">Example assistant answer from your project documents.</p>
                    @if (form.show_citations) {
                      <div class="mt-2 rounded-md border border-[var(--app-border)] bg-[var(--app-bg)] px-2 py-1.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-[var(--app-accent)]">Sources</p>
                        <p class="mt-1 text-xs text-[var(--app-text-muted)]">Example document · Pages 1-2</p>
                      </div>
                    }
                  </div>
                  <div class="rounded-lg border border-[var(--app-border)] px-3 py-2 text-xs text-[var(--app-text-muted)]">{{ form.input_placeholder }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-[var(--app-text)]">Embed snippet</h3>
              <button type="button" (click)="copyEmbedCode()" class="rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-xs font-medium text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">
                {{ copied() ? 'Copied' : 'Copy' }}
              </button>
            </div>
            <pre class="mt-4 overflow-x-auto rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3 text-xs text-[var(--app-text)]"><code>{{ embedSnippet() }}</code></pre>
          </div>
        </aside>
      </section>
    }
  `,
})
export class ProjectWidgetPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly error = signal('');
  protected readonly success = signal('');
  protected readonly copied = signal(false);
  protected readonly project = signal<Project | null>(null);

  protected form: WidgetSettings = this.defaultSettings();
  protected allowedDomainsText = '';
  protected suggestedQuestionsText = '';

  protected readonly suggestedQuestions = computed(() => this.lines(this.suggestedQuestionsText).slice(0, 6));
  protected readonly embedSnippet = computed(() => {
    const origin = typeof window !== 'undefined' ? window.location.origin : 'https://your-domain.com';
    const project = this.project();
    const widgetSecret = project?.widget_secret ?? 'YOUR_WIDGET_SECRET';

    return [
      '<!-- Zetes Chat Widget -->',
      `<script src="${origin}/widget/main.js"><\/script>`,
      '',
      '<zetes-chat',
      `  widget-key="${project?.widget_key ?? 'YOUR_WIDGET_KEY'}"`,
      `  widget-secret="${widgetSecret}"`,
      `  api-base-url="${origin}"`,
      '></zetes-chat>',
    ].join('\n');
  });

  ngOnInit(): void {
    const projectId = Number(this.route.parent?.snapshot.paramMap.get('projectId'));

    forkJoin([this.api.listProjects(), this.api.getWidgetSettings(projectId)]).subscribe({
      next: ([projects, settings]) => {
        this.project.set(projects.data.find((project) => project.id === projectId) ?? null);
        this.form = { ...this.defaultSettings(), ...settings.data };
        this.allowedDomainsText = this.form.allowed_domains.join('\n');
        this.suggestedQuestionsText = this.form.suggested_questions.join('\n');
      },
      complete: () => this.isLoading.set(false),
      error: () => {
        this.error.set('Unable to load widget settings.');
        this.isLoading.set(false);
      },
    });
  }

  protected save(): void {
    const projectId = Number(this.route.parent?.snapshot.paramMap.get('projectId'));
    this.error.set('');
    this.success.set('');
    this.isSaving.set(true);

    const payload: WidgetSettings = {
      ...this.form,
      language: this.form.language.trim().toLowerCase(),
      allowed_domains: this.lines(this.allowedDomainsText).slice(0, 20),
      suggested_questions: this.lines(this.suggestedQuestionsText).slice(0, 6),
    };

    this.api.updateWidgetSettings(projectId, payload).subscribe({
      next: ({ data }) => {
        this.form = { ...this.defaultSettings(), ...data };
        this.allowedDomainsText = this.form.allowed_domains.join('\n');
        this.suggestedQuestionsText = this.form.suggested_questions.join('\n');
        this.success.set('Widget settings saved.');
      },
      error: (error: HttpErrorResponse) => {
        this.error.set(error.error?.message ?? 'Unable to save widget settings.');
        this.isSaving.set(false);
      },
      complete: () => this.isSaving.set(false),
    });
  }

  protected copyEmbedCode(): void {
    this.copyText(this.embedSnippet()).then(() => {
      this.copied.set(true);
      setTimeout(() => this.copied.set(false), 2000);
    });
  }

  private async copyText(value: string): Promise<void> {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
      try {
        await navigator.clipboard.writeText(value);
        return;
      } catch {
        // Fall back for non-secure contexts or denied clipboard permission.
      }
    }

    if (typeof document === 'undefined') {
      return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
  }

  private lines(value: string): string[] {
    return value
      .split(/\r?\n/)
      .map((line) => line.trim())
      .filter((line) => line !== '');
  }

  private defaultSettings(): WidgetSettings {
    return {
      title: 'Chat with us',
      welcome_message: 'Ask a question about our documentation.',
      input_placeholder: 'Type your question...',
      primary_color: '#0891b2',
      position: 'bottom-right',
      theme: 'auto',
      language: 'en',
      show_citations: false,
      allowed_domains: [],
      suggested_questions: [],
    };
  }
}

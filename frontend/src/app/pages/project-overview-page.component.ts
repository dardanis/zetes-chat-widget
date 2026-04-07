import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { ChatSession, Project, ProjectDocument, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-overview-page',
  standalone: true,
  imports: [RouterLink],
  template: `
    @if (isLoading()) {
      <div class="grid gap-4 md:grid-cols-3">
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
      </div>
    } @else {
      <section class="space-y-6">
        <!-- Stats -->
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Project</p>
            <p class="mt-2 text-lg font-semibold text-[var(--app-text)]">{{ project()?.name ?? '-' }}</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Documents</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--app-text)]">{{ documents().length }}</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Chat sessions</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--app-text)]">{{ chats().length }}</p>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
          <h4 class="text-sm font-semibold text-[var(--app-text)]">Quick actions</h4>
          <div class="mt-3 flex flex-wrap gap-2">
            <a [routerLink]="['../documents']" class="inline-flex items-center gap-2 rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
              Manage documents
            </a>
            <a [routerLink]="['../chat']" class="inline-flex items-center gap-2 rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]">
              <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
              Open chat
            </a>
          </div>
        </div>

        <!-- Widget Embed Code -->
        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="text-sm font-semibold text-[var(--app-text)]">Widget embed code</h4>
              <p class="mt-1 text-xs text-[var(--app-text-muted)]">Add this snippet to any website to embed the chat widget.</p>
            </div>
            <button
              type="button"
              (click)="copyEmbedCode()"
              class="inline-flex items-center gap-1.5 rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-xs font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]"
            >
              @if (copySuccess()) {
                <svg class="h-3.5 w-3.5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                <span class="text-emerald-500">Copied!</span>
              } @else {
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path d="M8 2a1 1 0 000 2h2a1 1 0 100-2H8z"/><path d="M3 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v6h-4.586l1.293-1.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L10.414 13H15v3a2 2 0 01-2 2H5a2 2 0 01-2-2V5z"/></svg>
                Copy
              }
            </button>
          </div>

          <pre class="mt-4 overflow-x-auto rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-4 text-xs leading-relaxed text-[var(--app-text)]"><code>{{ embedSnippet() }}</code></pre>

          <div class="mt-3 flex items-start gap-2 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2.5 text-xs text-[var(--app-text-muted)]">
            <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            <span>Use <code class="rounded bg-[var(--app-surface)] px-1 py-0.5 text-[var(--app-text)]">widget-theme="auto"</code> for system theme sync, or set <code class="rounded bg-[var(--app-surface)] px-1 py-0.5 text-[var(--app-text)]">light</code>/<code class="rounded bg-[var(--app-surface)] px-1 py-0.5 text-[var(--app-text)]">dark</code> explicitly.</span>
          </div>
        </div>
      </section>
    }
  `,
})
export class ProjectOverviewPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly project = signal<Project | null>(null);
  protected readonly documents = signal<ProjectDocument[]>([]);
  protected readonly chats = signal<ChatSession[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly copySuccess = signal(false);

  protected readonly embedSnippet = computed(() => {
    const p = this.project();
    const widgetKey = p?.widget_key ?? 'YOUR_WIDGET_KEY';
    const widgetSecret = p?.widget_secret ?? 'YOUR_WIDGET_SECRET';
    const origin = typeof window !== 'undefined' ? window.location.origin : 'https://your-domain.com';

    return [
      `<!-- Zetes Chat Widget -->`,
      `<script src="${origin}/widget/main.js"><\/script>`,
      ``,
      `<zetes-chat`,
      `  widget-key="${widgetKey}"`,
      `  widget-secret="${widgetSecret}"`,
      `  widget-theme="auto"`,
      `  api-base-url="${origin}"`,
      `></zetes-chat>`,
    ].join('\n');
  });

  ngOnInit(): void {
    const projectId = Number(this.route.parent?.snapshot.paramMap.get('projectId'));

    forkJoin([
      this.api.listProjects(),
      this.api.listDocuments(projectId),
      this.api.listChats(projectId),
    ]).subscribe({
      next: ([projects, docs, chats]) => {
        this.project.set(projects.data.find((p) => p.id === projectId) ?? null);
        this.documents.set(docs.data);
        this.chats.set(chats.data);
      },
      error: () => this.isLoading.set(false),
      complete: () => this.isLoading.set(false),
    });
  }

  protected copyEmbedCode(): void {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
      navigator.clipboard.writeText(this.embedSnippet()).then(() => {
        this.copySuccess.set(true);
        setTimeout(() => this.copySuccess.set(false), 2000);
      });
    }
  }
}

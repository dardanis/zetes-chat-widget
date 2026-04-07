import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AuthService } from '../core/auth.service';
import { DashboardStats, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-dashboard-page',
  standalone: true,
  imports: [RouterLink],
  template: `
    <section class="space-y-6">
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-6">
        <h1 class="text-2xl font-semibold text-[var(--app-text)]">Dashboard</h1>
        <p class="mt-2 text-[var(--app-text-muted)]">Overview of your RAG workspace - tenants, projects, documents, and chat activity.</p>

        @if (auth.user(); as user) {
          <p class="mt-4 text-sm text-[var(--app-text-muted)]">Signed in as <span class="font-medium text-[var(--app-text)]">{{ user.name }}</span> ({{ user.email }})</p>
        }
      </div>

      @if (isLoading()) {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="h-28 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
          <div class="h-28 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
          <div class="h-28 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
          <div class="h-28 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        </div>
      } @else if (stats()) {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Tenants</p>
            <p class="mt-1 text-2xl font-bold text-[var(--app-text)]">{{ stats()!.total_tenants }}</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Projects</p>
            <p class="mt-1 text-2xl font-bold text-[var(--app-text)]">{{ stats()!.total_projects }}</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Documents</p>
            <p class="mt-1 text-2xl font-bold text-[var(--app-text)]">{{ stats()!.total_documents }}</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Chat sessions</p>
            <p class="mt-1 text-2xl font-bold text-[var(--app-text)]">{{ stats()!.total_chats }}</p>
          </div>
        </div>

        @if (hasDocumentStatuses()) {
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Documents by status</h3>
            <div class="mt-3 flex flex-wrap gap-3">
              @for (entry of documentStatusEntries(); track entry.status) {
                <div class="rounded-lg bg-[var(--app-surface-2)] px-4 py-2.5">
                  <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">{{ entry.status }}</p>
                  <p class="mt-1 text-lg font-semibold text-[var(--app-text)]">{{ entry.count }}</p>
                </div>
              }
            </div>
          </div>
        }

        <div class="grid gap-6 lg:grid-cols-2">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Recent projects</h3>

            <div class="mt-3 space-y-2">
              @for (project of stats()!.recent_projects; track project.id) {
                <a [routerLink]="['/app/projects', project.id, 'overview']" class="flex items-center justify-between gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-4 py-3 transition hover:opacity-90">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-[var(--app-text)]">{{ project.name }}</p>
                    <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ project.tenant?.name ?? 'Tenant #' + project.tenant_id }}</p>
                  </div>
                  <svg class="h-4 w-4 shrink-0 text-[var(--app-text-muted)]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                </a>
              } @empty {
                <p class="py-6 text-center text-sm text-[var(--app-text-muted)]">No projects yet.</p>
              }
            </div>
          </div>

          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Quick actions</h3>

            <div class="mt-3 space-y-2">
              <a routerLink="/app/tenants" class="flex items-center gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-4 py-3 transition hover:opacity-90">
                <div>
                  <p class="text-sm font-medium text-[var(--app-text)]">Manage tenants</p>
                  <p class="text-xs text-[var(--app-text-muted)]">Create, edit, or remove organizations</p>
                </div>
              </a>

              <a routerLink="/app/projects" class="flex items-center gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-4 py-3 transition hover:opacity-90">
                <div>
                  <p class="text-sm font-medium text-[var(--app-text)]">Manage projects</p>
                  <p class="text-xs text-[var(--app-text-muted)]">Upload documents, embed widgets, chat</p>
                </div>
              </a>

              <a routerLink="/app/settings" class="flex items-center gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-4 py-3 transition hover:opacity-90">
                <div>
                  <p class="text-sm font-medium text-[var(--app-text)]">Settings</p>
                  <p class="text-xs text-[var(--app-text-muted)]">Account info and configuration</p>
                </div>
              </a>
            </div>
          </div>
        </div>
      }
    </section>
  `,
})
export class DashboardPageComponent {
  protected readonly auth = inject(AuthService);
  private readonly api = inject(RagApiService);

  protected readonly stats = signal<DashboardStats | null>(null);
  protected readonly isLoading = signal(true);

  constructor() {
    this.api.getStats().subscribe({
      next: ({ data }) => this.stats.set(data),
      complete: () => this.isLoading.set(false),
      error: () => this.isLoading.set(false),
    });
  }

  protected hasDocumentStatuses(): boolean {
    const s = this.stats();
    return !!s && Object.keys(s.documents_by_status).length > 0;
  }

  protected documentStatusEntries(): { status: string; count: number }[] {
    const s = this.stats();
    if (!s) return [];
    return Object.entries(s.documents_by_status).map(([status, count]) => ({ status, count }));
  }
}

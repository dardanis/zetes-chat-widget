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
      <!-- Welcome -->
      <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-6">
        <h1 class="text-2xl font-semibold">Dashboard</h1>
        <p class="mt-2 text-slate-300">Overview of your RAG workspace — tenants, projects, documents, and chat activity.</p>

        @if (auth.user(); as user) {
          <p class="mt-4 text-sm text-slate-400">Signed in as <span class="font-medium text-slate-100">{{ user.name }}</span> ({{ user.email }})</p>
        }
      </div>

      <!-- Stats cards -->
      @if (isLoading()) {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="h-28 animate-pulse rounded-xl border border-slate-800 bg-slate-900/40"></div>
          <div class="h-28 animate-pulse rounded-xl border border-slate-800 bg-slate-900/40"></div>
          <div class="h-28 animate-pulse rounded-xl border border-slate-800 bg-slate-900/40"></div>
          <div class="h-28 animate-pulse rounded-xl border border-slate-800 bg-slate-900/40"></div>
        </div>
      } @else if (stats()) {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-400/10">
                <svg class="h-5 w-5 text-indigo-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
              </div>
              <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Tenants</p>
                <p class="mt-1 text-2xl font-bold text-slate-100">{{ stats()!.total_tenants }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-400/10">
                <svg class="h-5 w-5 text-cyan-400" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
              </div>
              <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Projects</p>
                <p class="mt-1 text-2xl font-bold text-slate-100">{{ stats()!.total_projects }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-400/10">
                <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
              </div>
              <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Documents</p>
                <p class="mt-1 text-2xl font-bold text-slate-100">{{ stats()!.total_documents }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-400/10">
                <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
              </div>
              <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">Chat sessions</p>
                <p class="mt-1 text-2xl font-bold text-slate-100">{{ stats()!.total_chats }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Documents by status -->
        @if (hasDocumentStatuses()) {
          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-sm font-semibold text-slate-200">Documents by status</h3>
            <div class="mt-3 flex flex-wrap gap-3">
              @for (entry of documentStatusEntries(); track entry.status) {
                <div class="rounded-lg bg-slate-800/60 px-4 py-2.5">
                  <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ entry.status }}</p>
                  <p class="mt-1 text-lg font-semibold text-slate-100">{{ entry.count }}</p>
                </div>
              }
            </div>
          </div>
        }

        <div class="grid gap-6 lg:grid-cols-2">
          <!-- Recent projects -->
          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-sm font-semibold text-slate-200">Recent projects</h3>

            <div class="mt-3 space-y-2">
              @for (project of stats()!.recent_projects; track project.id) {
                <a [routerLink]="['/app/projects', project.id, 'overview']" class="flex items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-950/40 px-4 py-3 transition hover:border-slate-700 hover:bg-slate-950/60">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-100">{{ project.name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ project.tenant?.name ?? 'Tenant #' + project.tenant_id }}</p>
                  </div>
                  <svg class="h-4 w-4 shrink-0 text-slate-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                </a>
              } @empty {
                <p class="py-6 text-center text-sm text-slate-500">No projects yet.</p>
              }
            </div>
          </div>

          <!-- Quick actions -->
          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-sm font-semibold text-slate-200">Quick actions</h3>

            <div class="mt-3 space-y-2">
              <a routerLink="/app/tenants" class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/40 px-4 py-3 transition hover:border-slate-700 hover:bg-slate-950/60">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-400/10">
                  <svg class="h-4 w-4 text-indigo-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-200">Manage tenants</p>
                  <p class="text-xs text-slate-500">Create, edit, or remove organizations</p>
                </div>
              </a>

              <a routerLink="/app/projects" class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/40 px-4 py-3 transition hover:border-slate-700 hover:bg-slate-950/60">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-cyan-400/10">
                  <svg class="h-4 w-4 text-cyan-400" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-200">Manage projects</p>
                  <p class="text-xs text-slate-500">Upload documents, embed widgets, chat</p>
                </div>
              </a>

              <a routerLink="/app/settings" class="flex items-center gap-3 rounded-lg border border-slate-800 bg-slate-950/40 px-4 py-3 transition hover:border-slate-700 hover:bg-slate-950/60">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-400/10">
                  <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                </div>
                <div>
                  <p class="text-sm font-medium text-slate-200">Settings</p>
                  <p class="text-xs text-slate-500">Account info and configuration</p>
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

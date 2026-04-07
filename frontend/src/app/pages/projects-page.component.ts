import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { Project, RagApiService, Tenant } from '../core/rag-api.service';

@Component({
  selector: 'app-projects-page',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <section class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold text-[var(--app-text)]">Projects</h2>
        <p class="mt-1 text-sm text-[var(--app-text-muted)]">Manage your RAG projects. Each project has its own document knowledge base and chat widget.</p>
      </div>

      <!-- Create project -->
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">Create project</h3>

        @if (createError()) {
          <p class="mt-3 rounded-md border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ createError() }}</p>
        }

        <div class="mt-3 flex flex-col gap-3 sm:flex-row">
          <select name="tenantId" [(ngModel)]="selectedTenantId" class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2">
            <option [ngValue]="null">Select tenant</option>
            @for (tenant of tenants(); track tenant.id) {
              <option [ngValue]="tenant.id">{{ tenant.name }}</option>
            }
          </select>
          <input name="projectName" [(ngModel)]="newProjectName" placeholder="Project name" class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition placeholder:text-[var(--app-text-muted)] focus:ring-2" />
          <button type="button" (click)="createProject()" [disabled]="isCreating()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
            {{ isCreating() ? 'Creating...' : 'Create project' }}
          </button>
        </div>
      </div>

      <!-- Project list -->
      @if (isLoading()) {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <div class="h-36 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
          <div class="h-36 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
          <div class="h-36 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        </div>
      } @else if (loadError()) {
        <div class="rounded-xl border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 p-6 text-center">
          <p class="text-sm text-[var(--app-danger)]">{{ loadError() }}</p>
          <button type="button" (click)="load()" class="mt-3 rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-4 py-2 text-sm text-[var(--app-text)] hover:opacity-90">Retry</button>
        </div>
      } @else {
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          @for (project of projects(); track project.id) {
            <div class="group rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5 transition hover:opacity-95">
              @if (editingId() === project.id) {
                <div class="space-y-3">
                  <input [(ngModel)]="editProjectName" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2" />
                  <div class="flex gap-2">
                    <button type="button" (click)="saveEdit(project)" [disabled]="isSaving()" class="rounded-lg bg-[var(--app-accent)] px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Save</button>
                    <button type="button" (click)="cancelEdit()" class="rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-sm text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
                  </div>
                </div>
              } @else if (deletingId() === project.id) {
                <div class="space-y-3">
                  <p class="text-sm text-[var(--app-danger)]">Delete <strong>{{ project.name }}</strong>? All documents and chats will be lost.</p>
                  <div class="flex gap-2">
                    <button type="button" (click)="confirmDelete(project)" [disabled]="isDeleting()" class="rounded-lg bg-[var(--app-danger)] px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Delete</button>
                    <button type="button" (click)="cancelDelete()" class="rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-sm text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
                  </div>
                </div>
              } @else {
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <h3 class="truncate text-sm font-semibold text-[var(--app-text)]">{{ project.name }}</h3>
                    <p class="mt-1 truncate text-xs text-[var(--app-text-muted)]">{{ project.slug }}</p>
                    <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">Widget key: {{ project.widget_key.slice(0, 12) }}…</p>
                  </div>
                  <svg class="h-4 w-4 shrink-0 text-[var(--app-text-muted)] transition group-hover:text-[var(--app-text)]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 border-t border-[var(--app-border)] pt-3">
                  <a [routerLink]="['/app/projects', project.id, 'overview']" class="rounded-md bg-[var(--app-accent-soft)] px-2.5 py-1 text-xs font-medium text-[var(--app-accent)] transition hover:opacity-90">Open</a>
                  <button type="button" (click)="startEdit(project)" class="rounded-md border border-[var(--app-border)] px-2.5 py-1 text-xs font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)]">Edit</button>
                  <button type="button" (click)="startDelete(project)" class="rounded-md border border-[var(--app-danger)]/50 px-2.5 py-1 text-xs font-medium text-[var(--app-danger)] transition hover:bg-[var(--app-danger)]/10">Delete</button>
                </div>
              }
            </div>
          } @empty {
            <div class="py-12 text-center sm:col-span-2 xl:col-span-3">
              <svg class="mx-auto h-10 w-10 text-[var(--app-text-muted)]" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
              <p class="mt-3 text-sm text-[var(--app-text-muted)]">No projects found</p>
              <p class="mt-1 text-xs text-[var(--app-text-muted)]">Create one above to get started.</p>
            </div>
          }
        </div>
      }
    </section>
  `,
})
export class ProjectsPageComponent {
  private readonly api = inject(RagApiService);

  protected readonly tenants = signal<Tenant[]>([]);
  protected readonly projects = signal<Project[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal('');
  protected readonly isCreating = signal(false);
  protected readonly isSaving = signal(false);
  protected readonly isDeleting = signal(false);
  protected readonly createError = signal('');
  protected readonly editingId = signal<number | null>(null);
  protected readonly deletingId = signal<number | null>(null);

  protected newProjectName = '';
  protected selectedTenantId: number | null = null;
  protected editProjectName = '';

  constructor() {
    this.load();
  }

  protected load(): void {
    this.isLoading.set(true);
    this.loadError.set('');

    forkJoin([this.api.listTenants(), this.api.listProjects()]).subscribe({
      next: ([tenants, projects]) => {
        this.tenants.set(tenants.data);
        this.projects.set(projects.data);
      },
      error: () => {
        this.loadError.set('Unable to load projects.');
        this.isLoading.set(false);
      },
      complete: () => this.isLoading.set(false),
    });
  }

  protected createProject(): void {
    if (!this.newProjectName.trim() || !this.selectedTenantId) {
      this.createError.set('Choose a tenant and provide a project name.');
      return;
    }

    this.isCreating.set(true);
    this.createError.set('');

    this.api.createProject({ tenant_id: this.selectedTenantId, name: this.newProjectName.trim() }).subscribe({
      next: ({ data }) => {
        this.projects.update((list) => [data, ...list]);
        this.newProjectName = '';
      },
      error: (err: HttpErrorResponse) => {
        this.createError.set(err.error?.message ?? 'Unable to create project.');
        this.isCreating.set(false);
      },
      complete: () => this.isCreating.set(false),
    });
  }

  protected startEdit(project: Project): void {
    this.editingId.set(project.id);
    this.editProjectName = project.name;
    this.deletingId.set(null);
  }

  protected cancelEdit(): void {
    this.editingId.set(null);
  }

  protected saveEdit(project: Project): void {
    if (!this.editProjectName.trim()) return;
    this.isSaving.set(true);

    this.api.updateProject(project.id, this.editProjectName.trim()).subscribe({
      next: ({ data }) => {
        this.projects.update((list) => list.map((p) => (p.id === data.id ? data : p)));
        this.editingId.set(null);
      },
      error: () => this.isSaving.set(false),
      complete: () => this.isSaving.set(false),
    });
  }

  protected startDelete(project: Project): void {
    this.deletingId.set(project.id);
    this.editingId.set(null);
  }

  protected cancelDelete(): void {
    this.deletingId.set(null);
  }

  protected confirmDelete(project: Project): void {
    this.isDeleting.set(true);

    this.api.deleteProject(project.id).subscribe({
      next: () => {
        this.projects.update((list) => list.filter((p) => p.id !== project.id));
        this.deletingId.set(null);
      },
      error: () => this.isDeleting.set(false),
      complete: () => this.isDeleting.set(false),
    });
  }
}

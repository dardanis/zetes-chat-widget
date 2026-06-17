import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { countryLabel } from '../core/countries';
import { Country, Project, RagApiService, Tenant } from '../core/rag-api.service';

@Component({
  selector: 'app-projects-page',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <section class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h2 class="text-xl font-semibold text-[var(--app-text)]">Projects</h2>
          <p class="mt-1 text-sm text-[var(--app-text-muted)]">Manage RAG projects by tenant and country.</p>
        </div>
        <button type="button" (click)="openCreateModal()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
          Create project
        </button>
      </div>

      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4">
        <label class="block">
          <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Search projects</span>
          <input name="projectSearch" [(ngModel)]="searchTerm" (ngModelChange)="searchProjects()" placeholder="Search by project, tenant, country, or status" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition placeholder:text-[var(--app-text-muted)] focus:ring-2" />
        </label>
      </div>

      @if (isCreateModalOpen()) {
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6 backdrop-blur-sm" (click)="closeCreateModal()">
          <form class="w-full max-w-2xl overflow-hidden rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] shadow-2xl" (click)="$event.stopPropagation()" (ngSubmit)="createProject()">
            <div class="flex items-start justify-between gap-4 border-b border-[var(--app-border)] px-5 py-4">
              <div>
                <h3 class="text-base font-semibold text-[var(--app-text)]">Create project</h3>
                <p class="mt-1 text-sm text-[var(--app-text-muted)]">Choose a tenant, country, and project name.</p>
              </div>
              <button type="button" (click)="closeCreateModal()" class="rounded-md px-2 py-1 text-sm text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Close</button>
            </div>

            <div class="space-y-4 px-5 py-4">
              @if (createError()) {
                <p class="rounded-md border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ createError() }}</p>
              }

              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Tenant</span>
                <select name="tenantId" [(ngModel)]="selectedTenantId" (ngModelChange)="syncCountryFromTenant()" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2">
                  <option [ngValue]="null">Select tenant</option>
                  @for (tenant of tenants(); track tenant.id) {
                    <option [ngValue]="tenant.id">{{ tenant.name }} · {{ labelCountry(tenant.country_code) }}</option>
                  }
                </select>
              </label>

              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Country</span>
                <select name="countryCode" [(ngModel)]="newProjectCountryCode" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2">
                  <option value="">Select country</option>
                  @for (country of countries(); track country.code) {
                    <option [value]="country.code">{{ country.name }} ({{ country.code }})</option>
                  }
                </select>
              </label>

              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Project name</span>
                <input name="projectName" [(ngModel)]="newProjectName" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2" />
              </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-[var(--app-border)] px-5 py-4">
              <button type="button" (click)="closeCreateModal()" class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm font-semibold text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
              <button type="submit" [disabled]="isCreating()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
                {{ isCreating() ? 'Creating...' : 'Create project' }}
              </button>
            </div>
          </form>
        </div>
      }

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
                  <select [(ngModel)]="editProjectCountryCode" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2">
                    @for (country of countries(); track country.code) {
                      <option [value]="country.code">{{ country.name }} ({{ country.code }})</option>
                    }
                  </select>
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
                    <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ labelCountry(project.country_code) }} · {{ project.status }}</p>
                  </div>
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
              <p class="mt-1 text-xs text-[var(--app-text-muted)]">Adjust search or create a new project.</p>
            </div>
          }
        </div>
      }
    </section>
  `,
})
export class ProjectsPageComponent {
  private readonly api = inject(RagApiService);

  protected readonly countries = signal<Country[]>([]);
  protected readonly tenants = signal<Tenant[]>([]);
  protected readonly projects = signal<Project[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly loadError = signal('');
  protected readonly isCreating = signal(false);
  protected readonly isSaving = signal(false);
  protected readonly isDeleting = signal(false);
  protected readonly createError = signal('');
  protected readonly isCreateModalOpen = signal(false);
  protected readonly editingId = signal<number | null>(null);
  protected readonly deletingId = signal<number | null>(null);

  protected newProjectName = '';
  protected newProjectCountryCode = '';
  protected selectedTenantId: number | null = null;
  protected editProjectName = '';
  protected editProjectCountryCode = '';
  protected searchTerm = '';
  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  constructor() {
    this.load();
  }

  protected load(): void {
    this.isLoading.set(true);
    this.loadError.set('');

    forkJoin([this.api.listTenants(), this.api.listProjects({ search: this.searchTerm.trim() }), this.api.listCountries()]).subscribe({
      next: ([tenants, projects, countries]) => {
        this.tenants.set(tenants.data);
        this.projects.set(projects.data);
        this.countries.set(countries.data);
      },
      error: () => {
        this.loadError.set('Unable to load projects.');
        this.isLoading.set(false);
      },
      complete: () => this.isLoading.set(false),
    });
  }

  protected createProject(): void {
    if (!this.newProjectName.trim() || !this.selectedTenantId || !this.newProjectCountryCode) {
      this.createError.set('Choose a tenant, country, and project name.');
      return;
    }

    this.isCreating.set(true);
    this.createError.set('');

    this.api.createProject({ tenant_id: this.selectedTenantId, country_code: this.newProjectCountryCode, name: this.newProjectName.trim(), status: 'active' }).subscribe({
      next: ({ data }) => {
        this.loadProjectResults();
        this.newProjectName = '';
        this.newProjectCountryCode = '';
        this.selectedTenantId = null;
        this.closeCreateModal();
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
    this.editProjectCountryCode = project.country_code;
    this.deletingId.set(null);
  }

  protected cancelEdit(): void {
    this.editingId.set(null);
  }

  protected saveEdit(project: Project): void {
    if (!this.editProjectName.trim() || !this.editProjectCountryCode) return;
    this.isSaving.set(true);

    this.api.updateProject(project.id, { name: this.editProjectName.trim(), country_code: this.editProjectCountryCode, status: project.status }).subscribe({
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

  protected syncCountryFromTenant(): void {
    const tenant = this.tenants().find((item) => item.id === this.selectedTenantId);
    this.newProjectCountryCode = tenant?.country_code ?? '';
  }

  protected searchProjects(): void {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }

    this.searchTimer = setTimeout(() => this.loadProjectResults(), 250);
  }

  private loadProjectResults(): void {
    this.isLoading.set(true);

    this.api.listProjects({ search: this.searchTerm.trim() }).subscribe({
      next: ({ data }) => this.projects.set(data),
      complete: () => this.isLoading.set(false),
      error: () => this.isLoading.set(false),
    });
  }

  protected labelCountry(code: string): string {
    return countryLabel(code, this.countries());
  }

  protected openCreateModal(): void {
    this.createError.set('');
    this.newProjectName = '';
    this.newProjectCountryCode = '';
    this.selectedTenantId = null;
    this.isCreateModalOpen.set(true);
  }

  protected closeCreateModal(): void {
    this.isCreateModalOpen.set(false);
    this.createError.set('');
  }
}

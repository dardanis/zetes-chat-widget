import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { countryLabel } from '../core/countries';
import { Country, RagApiService, Tenant } from '../core/rag-api.service';

@Component({
  selector: 'app-tenants-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h2 class="text-xl font-semibold text-[var(--app-text)]">Tenants</h2>
          <p class="mt-1 text-sm text-[var(--app-text-muted)]">Manage organizations by country.</p>
        </div>
        <button type="button" (click)="openCreateModal()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">
          Create tenant
        </button>
      </div>

      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4">
        <label class="block">
          <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Search tenants</span>
          <input name="tenantSearch" [(ngModel)]="searchTerm" placeholder="Search by tenant, country, or status" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition placeholder:text-[var(--app-text-muted)] focus:ring-2" />
        </label>
      </div>

      @if (isCreateModalOpen()) {
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6 backdrop-blur-sm" (click)="closeCreateModal()">
          <form class="w-full max-w-2xl overflow-hidden rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] shadow-2xl" (click)="$event.stopPropagation()" (ngSubmit)="create()">
            <div class="flex items-start justify-between gap-4 border-b border-[var(--app-border)] px-5 py-4">
              <div>
                <h3 class="text-base font-semibold text-[var(--app-text)]">Create tenant</h3>
                <p class="mt-1 text-sm text-[var(--app-text-muted)]">Choose a tenant name and country.</p>
              </div>
              <button type="button" (click)="closeCreateModal()" class="rounded-md px-2 py-1 text-sm text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Close</button>
            </div>

            <div class="space-y-4 px-5 py-4">
              @if (createError()) {
                <p class="rounded-md border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ createError() }}</p>
              }

              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Tenant name</span>
                <input name="tenantName" [(ngModel)]="newName" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2" />
              </label>

              <label class="block">
                <span class="mb-1 block text-xs font-medium text-[var(--app-text-muted)]">Country</span>
                <select name="tenantCountry" [(ngModel)]="newCountryCode" class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2">
                  <option value="">Select country</option>
                  @for (country of countries(); track country.code) {
                    <option [value]="country.code">{{ country.name }} ({{ country.code }})</option>
                  }
                </select>
              </label>
            </div>

            <div class="flex justify-end gap-2 border-t border-[var(--app-border)] px-5 py-4">
              <button type="button" (click)="closeCreateModal()" class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm font-semibold text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
              <button type="submit" [disabled]="isCreating()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
                {{ isCreating() ? 'Creating...' : 'Create tenant' }}
              </button>
            </div>
          </form>
        </div>
      }

      <!-- Tenant list -->
      @if (isLoading()) {
        <div class="space-y-3">
          <div class="h-20 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
          <div class="h-20 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        </div>
      } @else {
        <div class="space-y-3">
          @for (tenant of filteredTenants(); track tenant.id) {
            <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
              @if (editingId() === tenant.id) {
                <!-- Edit mode -->
                <div class="flex flex-col gap-3 md:flex-row">
                  <input [(ngModel)]="editName" class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2" />
                  <select [(ngModel)]="editCountryCode" class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 focus:ring-2">
                    @for (country of countries(); track country.code) {
                      <option [value]="country.code">{{ country.name }} ({{ country.code }})</option>
                    }
                  </select>
                  <button type="button" (click)="saveEdit(tenant)" [disabled]="isSaving()" class="rounded-lg bg-[var(--app-accent)] px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Save</button>
                  <button type="button" (click)="cancelEdit()" class="rounded-lg border border-[var(--app-border)] px-3 py-2 text-sm text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
                </div>
              } @else if (deletingId() === tenant.id) {
                <!-- Delete confirmation -->
                <div class="flex items-center justify-between">
                  <p class="text-sm text-[var(--app-danger)]">Delete <strong>{{ tenant.name }}</strong>? This will remove all projects and data.</p>
                  <div class="flex gap-2">
                    <button type="button" (click)="confirmDelete(tenant)" [disabled]="isDeleting()" class="rounded-lg bg-[var(--app-danger)] px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Delete</button>
                    <button type="button" (click)="cancelDelete()" class="rounded-lg border border-[var(--app-border)] px-3 py-2 text-sm text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
                  </div>
                </div>
              } @else {
                <!-- Display mode -->
                <div class="flex items-center justify-between gap-4">
                  <div>
                    <p class="font-medium text-[var(--app-text)]">{{ tenant.name }}</p>
                    <p class="mt-1 text-xs text-[var(--app-text-muted)]">{{ labelCountry(tenant.country_code) }} · {{ tenant.status }}</p>
                  </div>
                  <div class="flex gap-2">
                    <button type="button" (click)="startEdit(tenant)" class="rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-xs font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)]">Edit</button>
                    <button type="button" (click)="startDelete(tenant)" class="rounded-lg border border-[var(--app-danger)]/50 px-3 py-1.5 text-xs font-medium text-[var(--app-danger)] transition hover:bg-[var(--app-danger)]/10">Delete</button>
                  </div>
                </div>
              }
            </div>
          } @empty {
            <div class="py-12 text-center">
              <svg class="mx-auto h-10 w-10 text-[var(--app-text-muted)]" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
              <p class="mt-3 text-sm text-[var(--app-text-muted)]">No tenants found</p>
              <p class="mt-1 text-xs text-[var(--app-text-muted)]">Adjust search or create a new tenant.</p>
            </div>
          }
        </div>
      }
    </section>
  `,
})
export class TenantsPageComponent {
  private readonly api = inject(RagApiService);

  protected readonly countries = signal<Country[]>([]);
  protected readonly tenants = signal<Tenant[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly isCreating = signal(false);
  protected readonly isSaving = signal(false);
  protected readonly isDeleting = signal(false);
  protected readonly createError = signal('');
  protected readonly isCreateModalOpen = signal(false);
  protected readonly editingId = signal<number | null>(null);
  protected readonly deletingId = signal<number | null>(null);
  protected readonly filteredTenants = computed(() => {
    const term = this.searchTerm.trim().toLowerCase();

    if (!term) {
      return this.tenants();
    }

    return this.tenants().filter((tenant) => {
      const haystack = [
        tenant.name,
        tenant.status,
        tenant.country_code,
        this.labelCountry(tenant.country_code),
      ].join(' ').toLowerCase();

      return haystack.includes(term);
    });
  });

  protected newName = '';
  protected newCountryCode = '';
  protected editName = '';
  protected editCountryCode = '';
  protected searchTerm = '';

  constructor() {
    this.loadTenants();
  }

  protected create(): void {
    if (!this.newName.trim() || !this.newCountryCode) {
      this.createError.set('Provide tenant name and country.');
      return;
    }
    this.isCreating.set(true);
    this.createError.set('');

    this.api.createTenant({ name: this.newName.trim(), country_code: this.newCountryCode, status: 'active' }).subscribe({
      next: ({ data }) => {
        this.tenants.update((t) => [data, ...t]);
        this.newName = '';
        this.newCountryCode = '';
        this.closeCreateModal();
      },
      error: (err: HttpErrorResponse) => {
        this.createError.set(err.error?.message ?? 'Unable to create tenant.');
        this.isCreating.set(false);
      },
      complete: () => this.isCreating.set(false),
    });
  }

  protected startEdit(tenant: Tenant): void {
    this.editingId.set(tenant.id);
    this.editName = tenant.name;
    this.editCountryCode = tenant.country_code;
    this.deletingId.set(null);
  }

  protected cancelEdit(): void {
    this.editingId.set(null);
  }

  protected saveEdit(tenant: Tenant): void {
    if (!this.editName.trim() || !this.editCountryCode) return;
    this.isSaving.set(true);

    this.api.updateTenant(tenant.id, { name: this.editName.trim(), country_code: this.editCountryCode, status: tenant.status }).subscribe({
      next: ({ data }) => {
        this.tenants.update((list) => list.map((t) => (t.id === data.id ? data : t)));
        this.editingId.set(null);
      },
      error: () => this.isSaving.set(false),
      complete: () => this.isSaving.set(false),
    });
  }

  protected startDelete(tenant: Tenant): void {
    this.deletingId.set(tenant.id);
    this.editingId.set(null);
  }

  protected cancelDelete(): void {
    this.deletingId.set(null);
  }

  protected confirmDelete(tenant: Tenant): void {
    this.isDeleting.set(true);

    this.api.deleteTenant(tenant.id).subscribe({
      next: () => {
        this.tenants.update((list) => list.filter((t) => t.id !== tenant.id));
        this.deletingId.set(null);
      },
      error: () => this.isDeleting.set(false),
      complete: () => this.isDeleting.set(false),
    });
  }

  private loadTenants(): void {
    forkJoin([this.api.listTenants(), this.api.listCountries()]).subscribe({
      next: ([tenants, countries]) => {
        this.tenants.set(tenants.data);
        this.countries.set(countries.data);
      },
      complete: () => this.isLoading.set(false),
      error: () => this.isLoading.set(false),
    });
  }

  protected labelCountry(code: string): string {
    return countryLabel(code, this.countries());
  }

  protected openCreateModal(): void {
    this.createError.set('');
    this.newName = '';
    this.newCountryCode = '';
    this.isCreateModalOpen.set(true);
  }

  protected closeCreateModal(): void {
    this.isCreateModalOpen.set(false);
    this.createError.set('');
  }
}


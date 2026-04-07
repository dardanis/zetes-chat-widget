import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RagApiService, Tenant } from '../core/rag-api.service';

@Component({
  selector: 'app-tenants-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold">Tenants</h2>
        <p class="mt-1 text-sm text-slate-400">Manage your organizations. Each tenant can have multiple projects.</p>
      </div>

      <!-- Create tenant -->
      <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
        <h3 class="text-sm font-semibold text-slate-200">Create tenant</h3>

        @if (createError()) {
          <p class="mt-3 rounded-md border border-red-900 bg-red-950/60 px-3 py-2 text-sm text-red-200">{{ createError() }}</p>
        }

        <div class="mt-3 flex gap-2">
          <input name="tenantName" [(ngModel)]="newName" placeholder="Tenant name" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none ring-cyan-400/40 transition placeholder:text-slate-500 focus:border-slate-600 focus:ring-2" />
          <button type="button" (click)="create()" [disabled]="isCreating()" class="rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:opacity-60">
            {{ isCreating() ? 'Creating…' : 'Add tenant' }}
          </button>
        </div>
      </div>

      <!-- Tenant list -->
      @if (isLoading()) {
        <div class="space-y-3">
          <div class="h-20 animate-pulse rounded-xl border border-slate-800 bg-slate-900/40"></div>
          <div class="h-20 animate-pulse rounded-xl border border-slate-800 bg-slate-900/40"></div>
        </div>
      } @else {
        <div class="space-y-3">
          @for (tenant of tenants(); track tenant.id) {
            <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5">
              @if (editingId() === tenant.id) {
                <!-- Edit mode -->
                <div class="flex items-center gap-3">
                  <input [(ngModel)]="editName" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm outline-none ring-cyan-400/40 focus:border-slate-600 focus:ring-2" />
                  <button type="button" (click)="saveEdit(tenant)" [disabled]="isSaving()" class="rounded-lg bg-cyan-400 px-3 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-300 disabled:opacity-60">Save</button>
                  <button type="button" (click)="cancelEdit()" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancel</button>
                </div>
              } @else if (deletingId() === tenant.id) {
                <!-- Delete confirmation -->
                <div class="flex items-center justify-between">
                  <p class="text-sm text-red-200">Delete <strong>{{ tenant.name }}</strong>? This will remove all projects and data.</p>
                  <div class="flex gap-2">
                    <button type="button" (click)="confirmDelete(tenant)" [disabled]="isDeleting()" class="rounded-lg bg-red-500 px-3 py-2 text-sm font-semibold text-white hover:bg-red-400 disabled:opacity-60">Delete</button>
                    <button type="button" (click)="cancelDelete()" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Cancel</button>
                  </div>
                </div>
              } @else {
                <!-- Display mode -->
                <div class="flex items-center justify-between gap-4">
                  <div>
                    <p class="font-medium text-slate-100">{{ tenant.name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Tenant #{{ tenant.id }}</p>
                  </div>
                  <div class="flex gap-2">
                    <button type="button" (click)="startEdit(tenant)" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition hover:border-slate-500 hover:bg-slate-800">Edit</button>
                    <button type="button" (click)="startDelete(tenant)" class="rounded-lg border border-red-900/50 px-3 py-1.5 text-xs font-medium text-red-300 transition hover:border-red-700 hover:bg-red-950/40">Delete</button>
                  </div>
                </div>
              }
            </div>
          } @empty {
            <div class="py-12 text-center">
              <svg class="mx-auto h-10 w-10 text-slate-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
              <p class="mt-3 text-sm text-slate-400">No tenants yet</p>
              <p class="mt-1 text-xs text-slate-500">Create your first tenant above to get started.</p>
            </div>
          }
        </div>
      }
    </section>
  `,
})
export class TenantsPageComponent {
  private readonly api = inject(RagApiService);

  protected readonly tenants = signal<Tenant[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly isCreating = signal(false);
  protected readonly isSaving = signal(false);
  protected readonly isDeleting = signal(false);
  protected readonly createError = signal('');
  protected readonly editingId = signal<number | null>(null);
  protected readonly deletingId = signal<number | null>(null);

  protected newName = '';
  protected editName = '';

  constructor() {
    this.loadTenants();
  }

  protected create(): void {
    if (!this.newName.trim()) return;
    this.isCreating.set(true);
    this.createError.set('');

    this.api.createTenant(this.newName.trim()).subscribe({
      next: ({ data }) => {
        this.tenants.update((t) => [data, ...t]);
        this.newName = '';
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
    this.deletingId.set(null);
  }

  protected cancelEdit(): void {
    this.editingId.set(null);
  }

  protected saveEdit(tenant: Tenant): void {
    if (!this.editName.trim()) return;
    this.isSaving.set(true);

    this.api.updateTenant(tenant.id, this.editName.trim()).subscribe({
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
    this.api.listTenants().subscribe({
      next: ({ data }) => this.tenants.set(data),
      complete: () => this.isLoading.set(false),
      error: () => this.isLoading.set(false),
    });
  }
}


import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { forkJoin } from 'rxjs';
import { countryLabel } from '../core/countries';
import { Country, ManagedUser, RagApiService } from '../core/rag-api.service';

const roles = [
  { value: 'admin', label: 'Admin' },
  { value: 'manager', label: 'Manager' },
];

@Component({
  selector: 'app-users-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="space-y-6">
      <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
          <h2 class="text-xl font-semibold text-[var(--app-text)]">Users</h2>
          <p class="mt-1 text-sm text-[var(--app-text-muted)]">Manage user roles, status, and country access.</p>
        </div>
        @if (!accessDenied()) {
          <button type="button" (click)="openCreate()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90">New user</button>
        }
      </div>

      @if (accessDenied()) {
        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-8 text-center">
          <p class="text-sm font-semibold text-[var(--app-text)]">Users are admin-only</p>
          <p class="mt-2 text-sm text-[var(--app-text-muted)]">Managers can manage tenants and projects inside their assigned countries, but user management is restricted to admins.</p>
        </div>
      } @else if (error()) {
        <p class="rounded-md border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ error() }}</p>
      }

      @if (!accessDenied()) {
      <div class="overflow-hidden rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]">
        @if (isLoading()) {
          <div class="h-40 animate-pulse bg-[var(--app-surface-2)]"></div>
        } @else {
          <div class="hidden grid-cols-[1.4fr_150px_120px_1.5fr_150px] gap-4 border-b border-[var(--app-border)] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)] lg:grid">
            <span>User</span>
            <span>Role</span>
            <span>Status</span>
            <span>Countries</span>
            <span class="text-right">Actions</span>
          </div>
          <div class="divide-y divide-[var(--app-border)]">
            @for (user of users(); track user.id) {
              <article class="grid gap-4 px-4 py-4 lg:grid-cols-[1.4fr_150px_120px_1.5fr_150px] lg:items-center">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-[var(--app-text)]">{{ user.name }}</p>
                  <p class="truncate text-xs text-[var(--app-text-muted)]">{{ user.email }}</p>
                </div>
                <select [ngModel]="user.role" (ngModelChange)="updateUser(user, { role: $event })" class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)]">
                  @for (role of roles; track role.value) {
                    <option [value]="role.value">{{ role.label }}</option>
                  }
                </select>
                <select [ngModel]="user.status" (ngModelChange)="updateUser(user, { status: $event })" class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)]">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <div class="flex flex-wrap gap-1.5">
                  @for (code of user.country_codes; track code) {
                    <span class="rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-2 py-1 text-xs text-[var(--app-text-muted)]">{{ labelCountry(code) }}</span>
                  } @empty {
                    <span class="text-sm text-[var(--app-text-muted)]">No countries</span>
                  }
                </div>
                <div class="flex flex-wrap justify-start gap-2 lg:justify-end">
                  <button type="button" (click)="openEditCountries(user)" class="rounded-md border border-[var(--app-border)] px-2.5 py-1.5 text-xs font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)]">Countries</button>
                  <button type="button" (click)="deleteUser(user)" class="rounded-md border border-[var(--app-danger)]/50 px-2.5 py-1.5 text-xs font-medium text-[var(--app-danger)] transition hover:bg-[var(--app-danger)]/10">Delete</button>
                </div>
              </article>
            } @empty {
              <p class="p-8 text-center text-sm text-[var(--app-text-muted)]">No users found.</p>
            }
          </div>
        }
      </div>
      }

      @if (isModalOpen()) {
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6 backdrop-blur-sm" (click)="closeModal()">
          <form class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] shadow-2xl" (click)="$event.stopPropagation()" (ngSubmit)="saveModal()">
            <div class="border-b border-[var(--app-border)] px-5 py-4">
              <h3 class="text-base font-semibold text-[var(--app-text)]">{{ editingUser() ? 'Edit countries' : 'Create user' }}</h3>
            </div>
            <div class="overflow-y-auto p-5">
              @if (!editingUser()) {
                <div class="grid gap-3 md:grid-cols-2">
                  <input name="name" [(ngModel)]="form.name" placeholder="Name" class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)]" />
                  <input name="email" [(ngModel)]="form.email" placeholder="Email" type="email" class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)]" />
                  <input name="password" [(ngModel)]="form.password" placeholder="Temporary password" type="password" class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)]" />
                  <select name="role" [(ngModel)]="form.role" class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)]">
                    @for (role of roles; track role.value) {
                      <option [value]="role.value">{{ role.label }}</option>
                    }
                  </select>
                </div>
              }

              <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @for (country of countries(); track country.code) {
                  <label class="flex cursor-pointer items-center gap-2 rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] hover:border-[var(--app-accent)]/60">
                    <input type="checkbox" [checked]="form.country_codes.includes(country.code)" (change)="toggleCountry(country.code)" class="h-4 w-4 accent-[var(--app-accent)]" />
                    <span class="font-mono text-xs text-[var(--app-text-muted)]">{{ country.code }}</span>
                    <span>{{ country.name }}</span>
                  </label>
                }
              </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-[var(--app-border)] px-5 py-4">
              <button type="button" (click)="closeModal()" class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm font-semibold text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)]">Cancel</button>
              <button type="submit" [disabled]="isSaving()" class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-60">Save</button>
            </div>
          </form>
        </div>
      }
    </section>
  `,
})
export class UsersPageComponent {
  private readonly api = inject(RagApiService);

  protected readonly roles = roles;
  protected readonly users = signal<ManagedUser[]>([]);
  protected readonly countries = signal<Country[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly error = signal('');
  protected readonly accessDenied = signal(false);
  protected readonly isModalOpen = signal(false);
  protected readonly editingUser = signal<ManagedUser | null>(null);

  protected form = this.blankForm();

  constructor() {
    this.load();
  }

  protected openCreate(): void {
    this.editingUser.set(null);
    this.form = this.blankForm();
    this.isModalOpen.set(true);
  }

  protected openEditCountries(user: ManagedUser): void {
    this.editingUser.set(user);
    this.form = { ...this.blankForm(), country_codes: [...user.country_codes] };
    this.isModalOpen.set(true);
  }

  protected closeModal(): void {
    this.isModalOpen.set(false);
    this.error.set('');
  }

  protected saveModal(): void {
    const editing = this.editingUser();
    this.isSaving.set(true);
    this.error.set('');

    const request = editing
      ? this.api.updateUser(editing.id, { country_codes: this.form.country_codes })
      : this.api.createUser(this.form);

    request.subscribe({
      next: ({ data }) => {
        this.users.update((users) => editing ? users.map((user) => user.id === data.id ? data : user) : [data, ...users]);
        this.closeModal();
      },
      error: (error: HttpErrorResponse) => this.error.set(error.error?.message ?? 'Unable to save user.'),
      complete: () => this.isSaving.set(false),
    });
  }

  protected updateUser(user: ManagedUser, payload: Partial<{ role: string; status: string }>): void {
    this.api.updateUser(user.id, payload).subscribe({
      next: ({ data }) => this.users.update((users) => users.map((item) => item.id === data.id ? data : item)),
      error: (error: HttpErrorResponse) => this.error.set(error.error?.message ?? 'Unable to update user.'),
    });
  }

  protected deleteUser(user: ManagedUser): void {
    this.api.deleteUser(user.id).subscribe({
      next: () => this.users.update((users) => users.filter((item) => item.id !== user.id)),
      error: (error: HttpErrorResponse) => this.error.set(error.error?.message ?? 'Unable to delete user.'),
    });
  }

  protected toggleCountry(code: string): void {
    this.form.country_codes = this.form.country_codes.includes(code)
      ? this.form.country_codes.filter((item) => item !== code)
      : [...this.form.country_codes, code].sort();
  }

  protected labelCountry(code: string): string {
    return countryLabel(code, this.countries());
  }

  private load(): void {
    forkJoin([this.api.listUsers(), this.api.listCountries()]).subscribe({
      next: ([users, countries]) => {
        this.users.set(users.data);
        this.countries.set(countries.data);
      },
      error: (error: HttpErrorResponse) => {
        if (error.status === 403) {
          this.accessDenied.set(true);
          return;
        }

        this.error.set(error.error?.message ?? 'Unable to load users.');
      },
      complete: () => this.isLoading.set(false),
    });
  }

  private blankForm(): { name: string; email: string; password: string; role: string; status: string; country_codes: string[] } {
    return {
      name: '',
      email: '',
      password: '',
      role: 'manager',
      status: 'active',
      country_codes: [],
    };
  }
}

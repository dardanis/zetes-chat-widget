import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService } from '../core/auth.service';
import { RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-settings-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold text-[var(--app-text)]">Settings</h2>
        <p class="mt-1 text-sm text-[var(--app-text-muted)]">Account information and password security.</p>
      </div>

      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-6">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">Profile</h3>

        @if (auth.user(); as user) {
          <div class="mt-4 flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--app-accent-soft)] text-lg font-bold text-[var(--app-accent)]">{{ user.name.charAt(0).toUpperCase() }}</div>
            <div>
              <p class="font-medium text-[var(--app-text)]">{{ user.name }}</p>
              <p class="text-sm text-[var(--app-text-muted)]">{{ user.email }}</p>
            </div>
          </div>

          <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg bg-[var(--app-surface-2)] px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Email</dt>
              <dd class="mt-1 truncate text-sm text-[var(--app-text)]">{{ user.email }}</dd>
            </div>
          </dl>
        }
      </div>

      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-6">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Change password</h3>
            <p class="mt-1 text-sm text-[var(--app-text-muted)]">Update the password for your own account.</p>
          </div>
        </div>

        <form class="mt-5 grid gap-4 lg:max-w-2xl" (ngSubmit)="changePassword()">
          <label class="space-y-1.5">
            <span class="text-sm font-medium text-[var(--app-text)]">Current password</span>
            <input
              class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-bg)] px-3 py-2 text-sm text-[var(--app-text)] outline-none transition focus:border-[var(--app-accent)]"
              type="password"
              name="currentPassword"
              autocomplete="current-password"
              [(ngModel)]="passwordForm.current_password"
              required
            />
          </label>

          <div class="grid gap-4 sm:grid-cols-2">
            <label class="space-y-1.5">
              <span class="text-sm font-medium text-[var(--app-text)]">New password</span>
              <input
                class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-bg)] px-3 py-2 text-sm text-[var(--app-text)] outline-none transition focus:border-[var(--app-accent)]"
                type="password"
                name="newPassword"
                autocomplete="new-password"
                [(ngModel)]="passwordForm.password"
                required
                minlength="8"
              />
            </label>

            <label class="space-y-1.5">
              <span class="text-sm font-medium text-[var(--app-text)]">Confirm new password</span>
              <input
                class="w-full rounded-lg border border-[var(--app-border)] bg-[var(--app-bg)] px-3 py-2 text-sm text-[var(--app-text)] outline-none transition focus:border-[var(--app-accent)]"
                type="password"
                name="passwordConfirmation"
                autocomplete="new-password"
                [(ngModel)]="passwordForm.password_confirmation"
                required
                minlength="8"
              />
            </label>
          </div>

          @if (passwordError) {
            <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {{ passwordError }}
            </div>
          }

          @if (passwordSuccess) {
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
              {{ passwordSuccess }}
            </div>
          }

          <div class="flex justify-end">
            <button
              class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
              type="submit"
              [disabled]="savingPassword"
            >
              {{ savingPassword ? 'Saving...' : 'Change password' }}
            </button>
          </div>
        </form>
      </div>
    </section>
  `,
})
export class SettingsPageComponent {
  protected readonly auth = inject(AuthService);
  private readonly api = inject(RagApiService);

  protected savingPassword = false;
  protected passwordError = '';
  protected passwordSuccess = '';
  protected passwordForm = {
    current_password: '',
    password: '',
    password_confirmation: '',
  };

  protected changePassword(): void {
    this.passwordError = '';
    this.passwordSuccess = '';

    if (this.passwordForm.password !== this.passwordForm.password_confirmation) {
      this.passwordError = 'The new password confirmation does not match.';
      return;
    }

    this.savingPassword = true;

    this.api.changeOwnPassword(this.passwordForm).subscribe({
      next: (response) => {
        this.passwordSuccess = response.message;
        this.passwordForm = {
          current_password: '',
          password: '',
          password_confirmation: '',
        };
        this.savingPassword = false;
      },
      error: (error: HttpErrorResponse) => {
        this.passwordError = this.resolvePasswordError(error);
        this.savingPassword = false;
      },
    });
  }

  private resolvePasswordError(error: HttpErrorResponse): string {
    const validationErrors = error.error?.errors;

    if (validationErrors) {
      const firstError = Object.values(validationErrors).flat()[0];

      if (typeof firstError === 'string') {
        return firstError;
      }
    }

    if (typeof error.error?.message === 'string') {
      return error.error.message;
    }

    return 'Password could not be changed.';
  }
}

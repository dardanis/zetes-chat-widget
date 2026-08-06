import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../core/auth.service';

@Component({
  selector: 'app-login-page',
  standalone: true,
  imports: [FormsModule, RouterLink],
  template: `
    <section class="flex min-h-screen items-center justify-center px-4 py-12">
      <div class="w-full max-w-sm">
        <div class="app-panel rounded-[32px] p-7 shadow-[0_24px_60px_rgba(2,6,23,0.2)] sm:p-8">
          <div class="mb-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--app-accent-soft)] text-base font-bold text-[var(--app-accent)]">Z</div>
            <h1 class="mt-4 text-xl font-semibold tracking-tight text-[var(--app-text)]">Sign in to Zetes</h1>
            <p class="mt-1 text-sm text-[var(--app-text-muted)]">Enter your credentials to continue.</p>
          </div>

          @if (errorMessage()) {
            <div class="mb-4 flex items-start gap-2 rounded-2xl border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2.5 text-sm text-[var(--app-danger)]">
              <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
              <span>{{ errorMessage() }}</span>
            </div>
          }

          <form class="space-y-4" (ngSubmit)="submit()">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-[var(--app-text)]" for="email">Email</label>
              <input id="email" name="email" type="email" required [(ngModel)]="email" placeholder="you@example.com" class="app-input w-full rounded-2xl px-3 py-2.5 text-sm placeholder:text-[var(--app-text-muted)]" />
            </div>

            <div>
              <label class="mb-1.5 block text-sm font-medium text-[var(--app-text)]" for="password">Password</label>
              <input id="password" name="password" type="password" required [(ngModel)]="password" placeholder="••••••••" class="app-input w-full rounded-2xl px-3 py-2.5 text-sm placeholder:text-[var(--app-text-muted)]" />
            </div>

            <label class="flex items-center gap-2 text-sm text-[var(--app-text-muted)]">
              <input type="checkbox" name="remember" [(ngModel)]="remember" class="rounded border-[var(--app-border)] bg-[var(--app-surface)]" />
              Remember me
            </label>

            <button type="submit" [disabled]="isSubmitting()" class="app-interactive app-hover-lift w-full rounded-2xl bg-[var(--app-accent)] px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(34,211,238,0.18)] hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">
              {{ isSubmitting() ? 'Signing in...' : 'Sign in' }}
            </button>
          </form>

          <p class="mt-6 text-center text-sm text-[var(--app-text-muted)]">
            No account?
            <a routerLink="/register" class="font-medium text-[var(--app-accent)] hover:opacity-90">Create one</a>
          </p>
        </div>
      </div>
    </section>
  `,
})
export class LoginPageComponent {
  email = '';
  password = '';
  remember = false;

  readonly isSubmitting = signal(false);
  readonly errorMessage = signal('');

  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  submit(): void {
    this.isSubmitting.set(true);
    this.errorMessage.set('');

    this.auth.login({
      email: this.email,
      password: this.password,
      remember: this.remember,
    }).subscribe({
      next: () => {
        this.router.navigateByUrl('/dashboard');
      },
      error: (error: HttpErrorResponse) => {
        this.errorMessage.set(error.error?.message ?? 'Login failed. Check your credentials and try again.');
        this.isSubmitting.set(false);
      },
      complete: () => {
        this.isSubmitting.set(false);
      },
    });
  }
}


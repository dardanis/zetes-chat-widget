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
        <div class="mb-8 text-center">
          <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-400/15 text-base font-bold text-cyan-400">Z</div>
          <h1 class="mt-4 text-xl font-semibold">Sign in to Zetes</h1>
          <p class="mt-1 text-sm text-slate-400">Enter your credentials to continue.</p>
        </div>

        @if (errorMessage()) {
          <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-900/50 bg-red-950/40 px-3 py-2.5 text-sm text-red-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>{{ errorMessage() }}</span>
          </div>
        }

        <form class="space-y-4" (ngSubmit)="submit()">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-300" for="email">Email</label>
            <input id="email" name="email" type="email" required [(ngModel)]="email" placeholder="you@example.com" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm outline-none ring-cyan-400/40 transition placeholder:text-slate-500 focus:border-slate-600 focus:ring-2" />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-slate-300" for="password">Password</label>
            <input id="password" name="password" type="password" required [(ngModel)]="password" placeholder="••••••••" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm outline-none ring-cyan-400/40 transition placeholder:text-slate-500 focus:border-slate-600 focus:ring-2" />
          </div>

          <label class="flex items-center gap-2 text-sm text-slate-300">
            <input type="checkbox" name="remember" [(ngModel)]="remember" class="rounded border-slate-600 bg-slate-800" />
            Remember me
          </label>

          <button type="submit" [disabled]="isSubmitting()" class="w-full rounded-lg bg-cyan-400 px-4 py-2.5 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-60">
            {{ isSubmitting() ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">
          No account?
          <a routerLink="/register" class="font-medium text-cyan-400 hover:text-cyan-300">Create one</a>
        </p>
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


import { Component, inject } from '@angular/core';
import { AuthService } from '../core/auth.service';

@Component({
  selector: 'app-settings-page',
  standalone: true,
  template: `
    <section class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold text-[var(--app-text)]">Settings</h2>
        <p class="mt-1 text-sm text-[var(--app-text-muted)]">Account information and system configuration.</p>
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
              <dt class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">User ID</dt>
              <dd class="mt-1 text-sm text-[var(--app-text)]">{{ user.id }}</dd>
            </div>
            <div class="rounded-lg bg-[var(--app-surface-2)] px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Email</dt>
              <dd class="mt-1 truncate text-sm text-[var(--app-text)]">{{ user.email }}</dd>
            </div>
          </dl>
        }
      </div>

      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-6">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">System</h3>
        <p class="mt-2 text-sm text-[var(--app-text-muted)]">Additional configuration options will appear here as the product evolves.</p>
      </div>
    </section>
  `,
})
export class SettingsPageComponent {
  protected readonly auth = inject(AuthService);
}

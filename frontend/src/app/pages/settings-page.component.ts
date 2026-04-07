import { Component, inject } from '@angular/core';
import { AuthService } from '../core/auth.service';

@Component({
  selector: 'app-settings-page',
  standalone: true,
  template: `
    <section class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold">Settings</h2>
        <p class="mt-1 text-sm text-slate-400">Account information and system configuration.</p>
      </div>

      <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-6">
        <h3 class="text-sm font-semibold text-slate-200">Profile</h3>

        @if (auth.user(); as user) {
          <div class="mt-4 flex items-center gap-4">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan-400/10 text-lg font-bold text-cyan-400">{{ user.name.charAt(0).toUpperCase() }}</div>
            <div>
              <p class="font-medium text-slate-100">{{ user.name }}</p>
              <p class="text-sm text-slate-400">{{ user.email }}</p>
            </div>
          </div>

          <dl class="mt-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg bg-slate-800/40 px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">User ID</dt>
              <dd class="mt-1 text-sm text-slate-200">{{ user.id }}</dd>
            </div>
            <div class="rounded-lg bg-slate-800/40 px-4 py-3">
              <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Email</dt>
              <dd class="mt-1 truncate text-sm text-slate-200">{{ user.email }}</dd>
            </div>
          </dl>
        }
      </div>

      <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-6">
        <h3 class="text-sm font-semibold text-slate-200">System</h3>
        <p class="mt-2 text-sm text-slate-400">Additional configuration options will appear here as the product evolves.</p>
      </div>
    </section>
  `,
})
export class SettingsPageComponent {
  protected readonly auth = inject(AuthService);
}

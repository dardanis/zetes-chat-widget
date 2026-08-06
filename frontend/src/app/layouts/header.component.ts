import { Component, EventEmitter, Input, Output, inject } from '@angular/core';
import { ThemeService } from '../core/theme.service';

@Component({
  selector: 'app-header',
  standalone: true,
  template: `
    <header class="sticky top-0 z-30 border-b border-[var(--app-border)]/80 bg-[var(--app-surface)]/70 backdrop-blur-xl shadow-[0_1px_0_rgba(255,255,255,0.04)]">
      <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex items-center gap-3">
          <button type="button" (click)="toggleSidebar.emit()" class="app-interactive app-hover-lift inline-flex items-center justify-center rounded-full border border-[var(--app-border)] bg-[var(--app-surface-2)]/70 p-2 text-[var(--app-text-muted)] hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] lg:hidden" aria-label="Open navigation">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
          </button>

          <button type="button" (click)="toggleSidebar.emit()" class="app-interactive app-hover-lift hidden items-center justify-center rounded-full border border-[var(--app-border)] bg-[var(--app-surface-2)]/70 p-2 text-[var(--app-text-muted)] hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] lg:inline-flex" [attr.aria-label]="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
          </button>

          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-[var(--app-text)]">{{ title }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
          <button
            type="button"
            (click)="theme.toggleTheme()"
            class="app-interactive app-hover-lift rounded-full border border-[var(--app-border)] bg-[var(--app-surface-2)]/70 px-3 py-1.5 text-xs font-medium text-[var(--app-text-muted)] hover:bg-[var(--app-surface)] hover:text-[var(--app-text)]"
            [attr.aria-label]="theme.theme() === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
          >
            {{ theme.theme() === 'dark' ? 'Light mode' : 'Dark mode' }}
          </button>

          @if (userName) {
            <div class="hidden items-center gap-2 rounded-full border border-[var(--app-border)] bg-[var(--app-surface-2)]/70 px-2.5 py-1 sm:flex">
              <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--app-accent-soft)] text-xs font-semibold text-[var(--app-accent)]">{{ userName.charAt(0).toUpperCase() }}</div>
              <span class="text-sm text-[var(--app-text-muted)]">{{ userName }}</span>
            </div>
          }
          <button type="button" (click)="logout.emit()" class="app-interactive rounded-full px-3 py-1.5 text-sm font-medium text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)] hover:text-[var(--app-danger)]">Sign out</button>
        </div>
      </div>
    </header>
  `,
})
export class HeaderComponent {
  protected readonly theme = inject(ThemeService);

  @Input() title = 'Workspace';
  @Input() userName: string | null = null;
  @Input() sidebarCollapsed = false;

  @Output() logout = new EventEmitter<void>();
  @Output() toggleSidebar = new EventEmitter<void>();
}

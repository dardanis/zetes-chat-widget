import { Component, EventEmitter, Input, Output } from '@angular/core';

@Component({
  selector: 'app-header',
  standalone: true,
  template: `
    <header class="sticky top-0 z-30 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur-sm">
      <div class="flex h-14 items-center justify-between gap-4 px-4 sm:px-6">
        <div class="flex items-center gap-3">
          <!-- Mobile hamburger -->
          <button type="button" (click)="toggleSidebar.emit()" class="inline-flex items-center justify-center rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-slate-200 lg:hidden" aria-label="Open navigation">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
          </button>

          <!-- Desktop sidebar toggle -->
          <button type="button" (click)="toggleSidebar.emit()" class="hidden items-center justify-center rounded-md p-1.5 text-slate-400 hover:bg-slate-800 hover:text-slate-200 lg:inline-flex" [attr.aria-label]="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
          </button>

          <h1 class="text-sm font-medium text-slate-200">{{ title }}</h1>
        </div>

        <div class="flex items-center gap-3">
          @if (userName) {
            <div class="hidden items-center gap-2 sm:flex">
              <div class="flex h-7 w-7 items-center justify-center rounded-full bg-cyan-400/10 text-xs font-semibold text-cyan-400">{{ userName.charAt(0).toUpperCase() }}</div>
              <span class="text-sm text-slate-300">{{ userName }}</span>
            </div>
          }
          <button type="button" (click)="logout.emit()" class="rounded-md px-3 py-1.5 text-sm text-slate-400 transition hover:bg-slate-800 hover:text-red-300">Sign out</button>
        </div>
      </div>
    </header>
  `,
})
export class HeaderComponent {
  @Input() title = 'Workspace';
  @Input() userName: string | null = null;
  @Input() sidebarCollapsed = false;

  @Output() logout = new EventEmitter<void>();
  @Output() toggleSidebar = new EventEmitter<void>();
}

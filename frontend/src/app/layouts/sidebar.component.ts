import { Component, EventEmitter, Input, Output } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  template: `
    <!-- Mobile backdrop -->
    @if (mobileOpen) {
      <div class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" (click)="closeMobile.emit()"></div>
    }

    <aside [class]="sidebarClasses()">
      <!-- Brand -->
      <div class="flex h-14 shrink-0 items-center gap-3 border-b border-slate-800/60 px-4">
        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-cyan-400/15 text-sm font-bold text-cyan-400">Z</div>
        @if (!collapsed || mobileOpen) {
          <span class="truncate text-sm font-semibold text-slate-100">Zetes RAG</span>
        }

        @if (mobileOpen) {
          <button type="button" (click)="closeMobile.emit()" class="ml-auto rounded-md p-1 text-slate-400 hover:text-slate-200 lg:hidden" aria-label="Close menu">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        }
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
          <li>
            <a routerLink="/app/dashboard" routerLinkActive="bg-slate-800 text-cyan-300" [routerLinkActiveOptions]="{exact: false}" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
              <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
              @if (!collapsed || mobileOpen) { <span>Dashboard</span> }
            </a>
          </li>
          <li>
            <a routerLink="/app/tenants" routerLinkActive="bg-slate-800 text-cyan-300" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
              <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd"/></svg>
              @if (!collapsed || mobileOpen) { <span>Tenants</span> }
            </a>
          </li>
          <li>
            <a routerLink="/app/projects" routerLinkActive="bg-slate-800 text-cyan-300" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
              <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
              @if (!collapsed || mobileOpen) { <span>Projects</span> }
            </a>
          </li>
        </ul>

        @if (projectId) {
          <div class="mt-6">
            @if (!collapsed || mobileOpen) {
              <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Current project</p>
            } @else {
              <div class="mx-auto mb-2 h-px w-6 bg-slate-700"></div>
            }

            <ul class="space-y-1">
              <li>
                <a [routerLink]="['/app/projects', projectId, 'overview']" routerLinkActive="bg-slate-800 text-cyan-300" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
                  <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                  @if (!collapsed || mobileOpen) { <span>Overview</span> }
                </a>
              </li>
              <li>
                <a [routerLink]="['/app/projects', projectId, 'documents']" routerLinkActive="bg-slate-800 text-cyan-300" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
                  <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                  @if (!collapsed || mobileOpen) { <span>Documents</span> }
                </a>
              </li>
              <li>
                <a [routerLink]="['/app/projects', projectId, 'chat']" routerLinkActive="bg-slate-800 text-cyan-300" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
                  <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>
                  @if (!collapsed || mobileOpen) { <span>Chat</span> }
                </a>
              </li>
            </ul>
          </div>
        }

        <div class="mt-6">
          @if (!collapsed || mobileOpen) {
            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">System</p>
          } @else {
            <div class="mx-auto mb-2 h-px w-6 bg-slate-700"></div>
          }

          <ul class="space-y-1">
            <li>
              <a routerLink="/app/settings" routerLinkActive="bg-slate-800 text-cyan-300" class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800/70 hover:text-slate-200" (click)="onNavClick()">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
                @if (!collapsed || mobileOpen) { <span>Settings</span> }
              </a>
            </li>
          </ul>
        </div>
      </nav>
    </aside>
  `,
})
export class SidebarComponent {
  @Input() collapsed = false;
  @Input() mobileOpen = false;
  @Input() projectId: number | null = null;

  @Output() closeMobile = new EventEmitter<void>();

  protected sidebarClasses(): string {
    const base = 'flex flex-col border-r border-slate-800/80 bg-slate-900/95 transition-all duration-200';

    if (this.mobileOpen) {
      return `fixed inset-y-0 left-0 z-50 w-72 ${base}`;
    }

    const width = this.collapsed ? 'w-[68px]' : 'w-64';
    return `hidden lg:flex ${width} ${base}`;
  }

  protected onNavClick(): void {
    if (this.mobileOpen) {
      this.closeMobile.emit();
    }
  }
}

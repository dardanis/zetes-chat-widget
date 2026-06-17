import { Component, computed, inject, signal } from '@angular/core';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from '../core/auth.service';
import { HeaderComponent } from './header.component';
import { SidebarComponent } from './sidebar.component';

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [RouterOutlet, HeaderComponent, SidebarComponent],
  template: `
    <div class="flex h-screen overflow-hidden bg-[var(--app-bg)]">
      <app-sidebar
        [collapsed]="sidebarCollapsed()"
        [mobileOpen]="sidebarMobileOpen()"
        [projectId]="activeProjectId()"
        [showUsers]="auth.user()?.role === 'admin'"
        (closeMobile)="closeMobileSidebar()"
      />

      <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
        <app-header
          [title]="headerTitle()"
          [userName]="auth.user()?.name ?? null"
          [sidebarCollapsed]="sidebarCollapsed()"
          (toggleSidebar)="toggleSidebar()"
          (logout)="logout()"
        />

        <main class="flex-1 overflow-y-auto bg-[var(--app-surface-2)] px-4 py-6 sm:px-6 lg:px-8">
          <div class="mx-auto max-w-7xl">
            <router-outlet />
          </div>
        </main>
      </div>
    </div>
  `,
})
export class AppShellComponent {
  protected readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly sidebarCollapsed = signal(false);
  protected readonly sidebarMobileOpen = signal(false);
  protected readonly activeProjectId = signal<number | null>(this.parseProjectId(this.router.url));
  protected readonly headerTitle = computed(() =>
    this.activeProjectId() ? `Project #${this.activeProjectId()}` : 'Dashboard'
  );

  constructor() {
    this.router.events
      .pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd))
      .subscribe((event) => {
        this.activeProjectId.set(this.parseProjectId(event.urlAfterRedirects));
        this.sidebarMobileOpen.set(false);
      });
  }

  protected toggleSidebar(): void {
    if (typeof window !== 'undefined' && window.innerWidth < 1024) {
      this.sidebarMobileOpen.update((v) => !v);
    } else {
      this.sidebarCollapsed.update((v) => !v);
    }
  }

  protected closeMobileSidebar(): void {
    this.sidebarMobileOpen.set(false);
  }

  protected logout(): void {
    this.auth.logout().subscribe({
      next: () => this.router.navigateByUrl('/login'),
    });
  }

  private parseProjectId(url: string): number | null {
    const match = url.match(/\/app\/projects\/(\d+)/);
    return match ? Number(match[1]) : null;
  }
}

import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Project, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-layout',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <section class="space-y-6">
      <div>
        @if (isLoading()) {
          <div class="h-7 w-48 animate-pulse rounded bg-slate-800"></div>
          <div class="mt-2 h-4 w-72 animate-pulse rounded bg-slate-800/60"></div>
        } @else {
          <h2 class="text-xl font-semibold text-slate-100">{{ project()?.name ?? 'Project' }}</h2>
          <p class="mt-1 text-sm text-slate-400">Manage documents, review indexing, and chat with the knowledge base.</p>
        }

        <nav class="mt-5 flex gap-1">
          <a [routerLink]="['overview']" routerLinkActive="bg-slate-800 text-cyan-300" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-400 transition hover:bg-slate-800/50 hover:text-slate-200">Overview</a>
          <a [routerLink]="['documents']" routerLinkActive="bg-slate-800 text-cyan-300" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-400 transition hover:bg-slate-800/50 hover:text-slate-200">Documents</a>
          <a [routerLink]="['chat']" routerLinkActive="bg-slate-800 text-cyan-300" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-400 transition hover:bg-slate-800/50 hover:text-slate-200">Chat</a>
        </nav>
      </div>

      <router-outlet />
    </section>
  `,
})
export class ProjectLayoutComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly project = signal<Project | null>(null);
  protected readonly isLoading = signal(true);

  ngOnInit(): void {
    const projectId = Number(this.route.snapshot.paramMap.get('projectId'));

    this.api.listProjects().subscribe({
      next: ({ data }) => {
        this.project.set(data.find((project) => project.id === projectId) ?? null);
      },
      error: () => this.isLoading.set(false),
      complete: () => this.isLoading.set(false),
    });
  }
}

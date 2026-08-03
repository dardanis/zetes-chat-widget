import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { ProjectAnalytics, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-analytics-page',
  standalone: true,
  template: `
    @if (isLoading()) {
      <div class="grid gap-4 md:grid-cols-4">
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
        <div class="h-24 animate-pulse rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)]"></div>
      </div>
    } @else if (analytics(); as data) {
      <section class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Total chats</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--app-text)]">{{ data.total_chats }}</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Avg response</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--app-text)]">{{ data.average_response_time_seconds ?? '-' }}s</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Feedback score</p>
            <p class="mt-2 text-2xl font-semibold text-[var(--app-text)]">{{ data.feedback_score.positive_rate ?? '-' }}%</p>
          </div>
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Feedback</p>
            <p class="mt-2 text-sm text-[var(--app-text)]">{{ data.feedback_score.helpful }} helpful · {{ data.feedback_score.unhelpful }} not helpful</p>
          </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Most asked questions</h3>
            <div class="mt-4 space-y-3">
              @for (item of data.most_asked_questions; track item.question) {
                <div class="flex items-start justify-between gap-4 rounded-lg bg-[var(--app-surface-2)] px-3 py-2">
                  <p class="text-sm text-[var(--app-text)]">{{ item.question }}</p>
                  <span class="rounded-full bg-[var(--app-accent-soft)] px-2 py-0.5 text-xs font-semibold text-[var(--app-accent)]">{{ item.count }}</span>
                </div>
              } @empty {
                <p class="text-sm text-[var(--app-text-muted)]">No questions yet.</p>
              }
            </div>
          </div>

          <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Top referenced documents</h3>
            <div class="mt-4 space-y-3">
              @for (item of data.top_referenced_documents; track item.document_name) {
                <div class="flex items-center justify-between gap-4 rounded-lg bg-[var(--app-surface-2)] px-3 py-2">
                  <p class="truncate text-sm text-[var(--app-text)]">{{ item.document_name }}</p>
                  <span class="text-xs font-semibold text-[var(--app-text-muted)]">{{ item.count }}</span>
                </div>
              } @empty {
                <p class="text-sm text-[var(--app-text-muted)]">No referenced documents yet.</p>
              }
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold text-[var(--app-text)]">Phone channel</h3>
            <div class="flex flex-wrap gap-2 text-xs text-[var(--app-text-muted)]">
              @for (entry of channelEntries(); track entry.channel) {
                <span class="rounded-full border border-[var(--app-border)] px-2 py-0.5">{{ entry.channel }}: {{ entry.count }}</span>
              }
            </div>
          </div>

          @if (data.voice.total_calls === 0) {
            <p class="mt-4 text-sm text-[var(--app-text-muted)]">No calls yet.</p>
          } @else {
            <div class="mt-4 grid gap-4 md:grid-cols-3 xl:grid-cols-5">
              <div class="rounded-lg bg-[var(--app-surface-2)] px-3 py-2.5">
                <p class="text-xs text-[var(--app-text-muted)]">Total calls</p>
                <p class="mt-1 text-xl font-semibold text-[var(--app-text)]">{{ data.voice.total_calls }}</p>
              </div>
              <div class="rounded-lg bg-[var(--app-surface-2)] px-3 py-2.5">
                <p class="text-xs text-[var(--app-text-muted)]">Unique callers</p>
                <p class="mt-1 text-xl font-semibold text-[var(--app-text)]">{{ data.voice.unique_callers }}</p>
              </div>
              <div class="rounded-lg bg-[var(--app-surface-2)] px-3 py-2.5">
                <p class="text-xs text-[var(--app-text-muted)]">Avg duration</p>
                <p class="mt-1 text-xl font-semibold text-[var(--app-text)]">{{ data.voice.average_call_duration_seconds ?? '-' }}s</p>
              </div>
              <div class="rounded-lg bg-[var(--app-surface-2)] px-3 py-2.5">
                <p class="text-xs text-[var(--app-text-muted)]">Avg turns</p>
                <p class="mt-1 text-xl font-semibold text-[var(--app-text)]">{{ data.voice.average_turns_per_call ?? '-' }}</p>
              </div>
              <div class="rounded-lg bg-[var(--app-surface-2)] px-3 py-2.5">
                <p class="text-xs text-[var(--app-text-muted)]">Total minutes</p>
                <p class="mt-1 text-xl font-semibold text-[var(--app-text)]">{{ data.voice.total_minutes }}</p>
              </div>
            </div>

            @if (data.voice.top_calling_numbers.length > 0) {
              <div class="mt-4">
                <p class="text-xs font-medium uppercase tracking-wider text-[var(--app-text-muted)]">Top callers</p>
                <div class="mt-2 space-y-2">
                  @for (caller of data.voice.top_calling_numbers; track caller.from_number) {
                    <div class="flex items-center justify-between gap-4 rounded-lg bg-[var(--app-surface-2)] px-3 py-2">
                      <p class="font-mono text-sm text-[var(--app-text)]">{{ caller.from_number }}</p>
                      <span class="text-xs font-semibold text-[var(--app-text-muted)]">{{ caller.count }}</span>
                    </div>
                  }
                </div>
              </div>
            }
          }
        </div>

        <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
          <h3 class="text-sm font-semibold text-[var(--app-text)]">Failed / no-answer questions</h3>
          <div class="mt-4 space-y-3">
            @for (item of data.failed_no_answer_questions; track item.question + item.created_at) {
              <div class="rounded-lg bg-[var(--app-surface-2)] px-3 py-2">
                <p class="text-sm font-medium text-[var(--app-text)]">{{ item.question }}</p>
                <p class="mt-1 line-clamp-2 text-xs text-[var(--app-text-muted)]">{{ item.answer }}</p>
              </div>
            } @empty {
              <p class="text-sm text-[var(--app-text-muted)]">No failed answers detected.</p>
            }
          </div>
        </div>
      </section>
    } @else {
      <p class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5 text-sm text-[var(--app-text-muted)]">Unable to load analytics.</p>
    }
  `,
})
export class ProjectAnalyticsPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly analytics = signal<ProjectAnalytics | null>(null);
  protected readonly isLoading = signal(true);

  protected readonly channelEntries = computed(() =>
    Object.entries(this.analytics()?.chats_by_channel ?? {}).map(([channel, count]) => ({ channel, count }))
  );

  ngOnInit(): void {
    const projectId = Number(this.route.parent?.snapshot.paramMap.get('projectId'));

    this.api.getProjectAnalytics(projectId).subscribe({
      next: ({ data }) => this.analytics.set(data),
      complete: () => this.isLoading.set(false),
      error: () => this.isLoading.set(false),
    });
  }
}

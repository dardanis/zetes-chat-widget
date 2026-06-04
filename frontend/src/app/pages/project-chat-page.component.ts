import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { RealtimeChatEventPayload, ReverbService } from '../core/reverb.service';
import { ChatMessage, ChatSession, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-chat-page',
  standalone: true,
  imports: [FormsModule, DatePipe],
  template: `
    <section class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
      <aside class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-[var(--app-text)]">Chat sessions</h3>
          <button type="button" (click)="loadChats()" class="text-xs text-[var(--app-accent)] hover:opacity-90">Refresh</button>
        </div>

        <div class="mt-3 flex gap-2">
          <input [(ngModel)]="chatTitle" placeholder="Session title" class="min-w-0 flex-1 rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-2 py-1.5 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/60 focus:ring" />
          <button type="button" (click)="createChat()" [disabled]="isCreatingChat()" class="rounded-md bg-[var(--app-accent)] px-3 py-1.5 text-sm font-medium text-white hover:opacity-90 disabled:opacity-60">+</button>
        </div>

        <div class="mt-4 space-y-2">
          @for (chat of chats(); track chat.id) {
            <button type="button" (click)="selectChat(chat)" [class]="selectedChatId() === chat.id ? 'w-full rounded-md border border-[var(--app-accent)]/50 bg-[var(--app-accent-soft)] px-3 py-2 text-left text-sm text-[var(--app-accent)]' : 'w-full rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-left text-sm text-[var(--app-text-muted)] hover:opacity-90'">
              <p class="truncate font-medium">{{ chatDisplayTitle(chat) }}</p>

              @if (chatDisplaySubtitle(chat)) {
                <p class="mt-1 truncate text-xs opacity-80">{{ chatDisplaySubtitle(chat) }}</p>
              }

              @if (chatDisplayTimestamp(chat)) {
                <p class="mt-1 text-xs opacity-70">{{ chatDisplayTimestamp(chat) | date:'MMM d, y, h:mm a' }}</p>
              }
            </button>
          } @empty {
            <p class="text-sm text-[var(--app-text-muted)]">No sessions yet.</p>
          }
        </div>
      </aside>

      <section class="flex min-h-[560px] flex-col rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4">
        <div class="mb-4 border-b border-[var(--app-border)] pb-3">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-[var(--app-text)]">Conversation</h3>
              @if (activeChat()) {
                <p class="text-sm text-[var(--app-text-muted)]">{{ chatDisplayTitle(activeChat()!) }}</p>

                @if (chatDisplaySubtitle(activeChat()!)) {
                  <p class="mt-1 text-xs text-[var(--app-text-muted)]">{{ chatDisplaySubtitle(activeChat()!) }}</p>
                }

                @if (chatDisplayTimestamp(activeChat()!)) {
                  <p class="mt-1 text-xs text-[var(--app-text-muted)]">Last request: {{ chatDisplayTimestamp(activeChat()!) | date:'MMM d, y, h:mm a' }}</p>
                }
              }
            </div>

            <div class="flex items-center gap-2 text-xs">
              <span [class]="isRealtimeConnected() ? 'rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 font-medium text-emerald-600' : 'rounded-full border border-[var(--app-border)] bg-[var(--app-surface-2)] px-2 py-1 font-medium text-[var(--app-text-muted)]'">
                {{ isRealtimeConnected() ? 'Live updates on' : 'Live updates off' }}
              </span>
            </div>
          </div>
        </div>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
          @for (message of chatMessages(); track message.id) {
            <article [class]="message.role === 'assistant' ? 'rounded-lg border border-[var(--app-accent)]/20 bg-[var(--app-surface-2)] p-3' : 'rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3'">
              <p class="text-xs uppercase tracking-wide text-[var(--app-text-muted)]">{{ message.role }}</p>
              <p class="mt-2 whitespace-pre-wrap text-sm text-[var(--app-text)]">{{ message.content }}</p>

              @if (message.role === 'assistant' && (message.citations?.length ?? 0) > 0) {
                <div class="mt-3 rounded-md border border-[var(--app-border)] bg-[var(--app-surface)] p-2">
                  <p class="text-xs uppercase tracking-wide text-[var(--app-accent)]">Sources</p>
                  <div class="mt-2 space-y-2 text-xs text-[var(--app-text-muted)]">
                    @for (citation of message.citations ?? []; track $index) {
                      <div>
                        <p class="font-medium text-[var(--app-text)]">{{ citation.document_name ?? citation.metadata?.document_name ?? 'Document' }}</p>
                        <p>Pages {{ citation.page_from ?? citation.metadata?.page_from ?? '?' }}-{{ citation.page_to ?? citation.metadata?.page_to ?? '?' }}</p>
                        <p>{{ citation.excerpt ?? citation.metadata?.excerpt ?? '' }}</p>
                      </div>
                    }
                  </div>
                </div>
              }
            </article>
          } @empty {
            <p class="text-sm text-[var(--app-text-muted)]">Create or select a chat session to start asking questions.</p>
          }
        </div>

        @if (chatError()) {
          <p class="mt-3 rounded-md border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ chatError() }}</p>
        }

        <div class="mt-4 flex gap-2 border-t border-[var(--app-border)] pt-3">
          <textarea [(ngModel)]="messageDraft" rows="3" placeholder="Ask about project documents..." [disabled]="!activeChat() || isSending()" class="min-h-[78px] flex-1 rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/60 focus:ring disabled:opacity-60"></textarea>
          <button type="button" (click)="sendMessage()" [disabled]="!activeChat() || !messageDraft.trim() || isSending()" class="self-end rounded-md bg-[var(--app-accent)] px-4 py-2 text-sm font-medium text-white hover:opacity-90 disabled:opacity-60">{{ isSending() ? 'Sending...' : 'Send' }}</button>
        </div>
      </section>
    </section>
  `,
})
export class ProjectChatPageComponent implements OnInit, OnDestroy {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);
  private readonly reverb = inject(ReverbService);

  protected readonly chats = signal<ChatSession[]>([]);
  protected readonly chatMessages = signal<ChatMessage[]>([]);
  protected readonly selectedChatId = signal<number | null>(null);
  protected readonly isCreatingChat = signal(false);
  protected readonly isSending = signal(false);
  protected readonly isRealtimeConnected = signal(false);
  protected readonly chatError = signal('');
  protected chatTitle = '';
  protected messageDraft = '';

  protected readonly activeChat = computed(() => this.chats().find((chat) => chat.id === this.selectedChatId()) ?? null);

  private realtimeSubscription: { leave: () => void } | null = null;
  private realtimeToken = 0;

  ngOnInit(): void {
    this.loadChats();
  }

  ngOnDestroy(): void {
    this.leaveRealtime();
  }

  protected loadChats(): void {
    this.api.listChats(this.requireProjectId()).subscribe(({ data }) => {
      this.chats.set(data);

      if (!this.selectedChatId() && data.length > 0) {
        this.selectChat(data[0]);
      }
    });
  }

  protected createChat(): void {
    this.isCreatingChat.set(true);
    this.chatError.set('');

    this.api.createChat(this.requireProjectId(), this.chatTitle.trim() || 'New chat').subscribe({
      next: ({ data }) => {
        this.chats.update((sessions) => [data, ...sessions]);
        this.chatTitle = '';
        this.selectChat(data);
      },
      error: (error: HttpErrorResponse) => {
        this.chatError.set(error.error?.message ?? 'Unable to create chat session.');
        this.isCreatingChat.set(false);
      },
      complete: () => this.isCreatingChat.set(false),
    });
  }

  protected selectChat(chat: ChatSession): void {
    this.selectedChatId.set(chat.id);
    this.chatError.set('');

    this.api.getChatHistory(this.requireProjectId(), chat.id).subscribe({
      next: ({ data }) => this.chatMessages.set(data),
      error: () => this.chatError.set('Unable to load chat history.'),
    });

    void this.bindRealtime(chat);
  }

  protected sendMessage(): void {
    const activeChat = this.activeChat();
    const message = this.messageDraft.trim();

    if (!activeChat || !message) {
      return;
    }

    this.isSending.set(true);
    this.chatError.set('');

    this.api.sendMessage(this.requireProjectId(), activeChat.id, message).subscribe({
      next: ({ data }) => {
        this.chatMessages.update((messages) => [
          ...messages,
          data.user_message,
          {
            ...data.assistant_message,
            citations: data.citations,
          },
        ]);
        this.messageDraft = '';
      },
      error: (error: HttpErrorResponse) => {
        this.chatError.set(error.error?.message ?? 'Unable to send message.');
        this.isSending.set(false);
      },
      complete: () => this.isSending.set(false),
    });
  }

  protected chatDisplayTitle(chat: ChatSession): string {
    const widgetEmail = chat.metadata?.widget_user?.email?.trim();

    if (chat.channel === 'widget' && widgetEmail) {
      return widgetEmail;
    }

    return chat.title?.trim() || `Chat #${chat.id}`;
  }

  protected chatDisplaySubtitle(chat: ChatSession): string {
    const details: string[] = [];
    const widgetUser = chat.metadata?.widget_user;

    if (chat.channel === 'widget') {
      details.push('Widget chat');

      if (widgetUser?.token_present) {
        details.push(widgetUser.email ? 'token matched to email' : 'token detected');
      } else if (widgetUser?.email) {
        details.push('email provided');
      }
    } else if (chat.channel) {
      details.push(chat.channel);
    }

    if (widgetUser?.subject) {
      details.push(`sub: ${widgetUser.subject}`);
    }

    return details.join(' • ');
  }

  protected chatDisplayTimestamp(chat: ChatSession): string | null {
    return chat.metadata?.widget_last_request_at ?? chat.updated_at ?? chat.created_at ?? null;
  }

  private async bindRealtime(chat: ChatSession): Promise<void> {
    const token = ++this.realtimeToken;

    this.leaveRealtime();
    this.isRealtimeConnected.set(false);

    const subscription = await this.reverb.subscribeToProjectChat(this.requireProjectId(), chat.id, {
      onConnected: () => {
        if (token === this.realtimeToken) {
          this.isRealtimeConnected.set(true);
        }
      },
      onMessage: (payload: RealtimeChatEventPayload) => {
        if (token !== this.realtimeToken) {
          return;
        }

        this.mergeRealtimePayload(payload);
      },
      onError: (message: string) => {
        if (token === this.realtimeToken) {
          this.chatError.set(message);
        }
      },
    });

    if (token !== this.realtimeToken) {
      subscription?.leave();
      return;
    }

    this.realtimeSubscription = subscription;
  }

  private mergeRealtimePayload(payload: RealtimeChatEventPayload): void {
    const messages = [payload.user_message, payload.assistant_message, payload.message, payload.data]
      .filter((message): message is ChatMessage => !!message)
      .map((message) => ({
        ...message,
        citations: message.citations ?? payload.citations,
      }));

    if (messages.length === 0) {
      return;
    }

    this.chatMessages.update((existing) => {
      const next = [...existing];

      for (const message of messages) {
        const matchIndex = next.findIndex((item) => item.id === message.id);

        if (matchIndex >= 0) {
          next[matchIndex] = message;
        } else {
          next.push(message);
        }
      }

      return next;
    });
  }

  private leaveRealtime(): void {
    this.realtimeSubscription?.leave();
    this.realtimeSubscription = null;
    this.isRealtimeConnected.set(false);
  }

  private requireProjectId(): number {
    return Number(this.route.parent?.snapshot.paramMap.get('projectId'));
  }
}


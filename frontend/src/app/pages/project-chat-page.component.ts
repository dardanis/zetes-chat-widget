import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { ChatMessage, ChatSession, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-chat-page',
  standalone: true,
  imports: [FormsModule],
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
              {{ chat.title || ('Chat #' + chat.id) }}
            </button>
          } @empty {
            <p class="text-sm text-[var(--app-text-muted)]">No sessions yet.</p>
          }
        </div>
      </aside>

      <section class="flex min-h-[560px] flex-col rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-4">
        <div class="mb-4 border-b border-[var(--app-border)] pb-3">
          <h3 class="text-lg font-semibold text-[var(--app-text)]">Conversation</h3>
          @if (activeChat()) {
            <p class="text-sm text-[var(--app-text-muted)]">{{ activeChat()?.title || ('Chat #' + activeChat()?.id) }}</p>
          }
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
export class ProjectChatPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly chats = signal<ChatSession[]>([]);
  protected readonly chatMessages = signal<ChatMessage[]>([]);
  protected readonly selectedChatId = signal<number | null>(null);
  protected readonly isCreatingChat = signal(false);
  protected readonly isSending = signal(false);
  protected readonly chatError = signal('');
  protected chatTitle = '';
  protected messageDraft = '';

  protected readonly activeChat = computed(() => this.chats().find((chat) => chat.id === this.selectedChatId()) ?? null);

  ngOnInit(): void {
    this.loadChats();
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

  private requireProjectId(): number {
    return Number(this.route.parent?.snapshot.paramMap.get('projectId'));
  }
}


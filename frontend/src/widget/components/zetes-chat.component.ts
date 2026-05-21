import {
  Component,
  ElementRef,
  Input,
  OnChanges,
  OnDestroy,
  OnInit,
  SimpleChanges,
  ViewEncapsulation,
  inject,
  signal,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ChatMessage, WidgetApiService } from '../services/widget-api.service';

type WidgetTheme = 'auto' | 'dark' | 'light';

@Component({
  selector: 'zetes-chat-widget',
  standalone: true,
  imports: [FormsModule],
  encapsulation: ViewEncapsulation.ShadowDom,
  providers: [WidgetApiService],
  host: {
    '[attr.data-theme]': 'activeTheme',
  },
  template: `
    <!-- Floating trigger button -->
    @if (!panelOpen()) {
      <button (click)="openPanel()" class="trigger-btn" aria-label="Open chat">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
      </button>
    }

    <!-- Chat panel -->
    @if (panelOpen()) {
      <div class="panel">
        <!-- Header -->
        <div class="panel-header">
          <div class="header-brand">
            <div class="brand-icon">Z</div>
            <span class="header-title">Chat with us</span>
          </div>
          <button (click)="closePanel()" class="close-btn" aria-label="Close chat">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>

        <!-- Messages area -->
        <div class="messages-area" #messagesArea>
          @if (isCreatingSession()) {
            <div class="status-message">
              <div class="spinner"></div>
              <span>Starting chat session...</span>
            </div>
          } @else if (sessionError()) {
            <div class="status-message error-text">
              <span>{{ sessionError() }}</span>
              <button (click)="retrySession()" class="retry-btn">Retry</button>
            </div>
          } @else if (messages().length === 0) {
            <div class="status-message">
              <span>Ask a question about our documentation.</span>
            </div>
          }

          @for (msg of messages(); track msg.id) {
            <div [class]="msg.role === 'assistant' ? 'msg msg-assistant' : 'msg msg-user'">
              <div class="msg-role">{{ msg.role === 'assistant' ? 'Assistant' : 'You' }}</div>
              <div class="msg-content">{{ msg.content }}</div>
            </div>
          }

          @if (isSending()) {
            <div class="msg msg-assistant">
              <div class="msg-role">Assistant</div>
              <div class="msg-content thinking">
                <span class="dot"></span><span class="dot"></span><span class="dot"></span>
              </div>
            </div>
          }
        </div>

        <!-- Input area -->
        <div class="input-area">
          @if (chatError()) {
            <div class="chat-error">{{ chatError() }}</div>
          }
          <div class="input-row">
            <textarea
              class="msg-input"
              rows="2"
              placeholder="Type your question..."
              [disabled]="!api.hasSession || isSending()"
              [(ngModel)]="draft"
              (keydown.enter)="onEnterKey($event)"
            ></textarea>
            <button
              class="send-btn"
              [disabled]="!api.hasSession || !draft.trim() || isSending()"
              (click)="send()"
              aria-label="Send message"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
            </button>
          </div>
          <div class="powered-by">Powered by <strong>Zetes</strong></div>
        </div>
      </div>
    }
  `,
  styles: [`
    :host {
      --zc-primary: #22d3ee;
      --zc-primary-hover: #67e8f9;
      --zc-bg: #0f172a;
      --zc-surface: #1e293b;
      --zc-surface-alt: #334155;
      --zc-border: #334155;
      --zc-text: #f1f5f9;
      --zc-text-muted: #94a3b8;
      --zc-text-dim: #64748b;
      --zc-error: #fca5a5;
      --zc-error-bg: rgba(127, 29, 29, 0.3);

      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 999999;
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      font-size: 14px;
      line-height: 1.5;
      color: var(--zc-text);
    }

    :host([data-theme='light']) {
      --zc-primary: #0891b2;
      --zc-primary-hover: #0e7490;
      --zc-bg: #ffffff;
      --zc-surface: #f8fafc;
      --zc-surface-alt: #f1f5f9;
      --zc-border: #cbd5e1;
      --zc-text: #0f172a;
      --zc-text-muted: #475569;
      --zc-text-dim: #64748b;
      --zc-error: #dc2626;
      --zc-error-bg: rgba(220, 38, 38, 0.1);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    /* Trigger button */
    .trigger-btn {
      width: 56px; height: 56px;
      border-radius: 50%;
      border: none;
      background: var(--zc-primary);
      color: #ffffff;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 24px rgba(0,0,0,0.35);
      transition: background 0.2s, transform 0.2s;
    }
    .trigger-btn:hover { background: var(--zc-primary-hover); transform: scale(1.05); }

    /* Panel */
    .panel {
      width: 380px; max-width: calc(100vw - 32px);
      height: 560px; max-height: calc(100vh - 40px);
      border-radius: 16px;
      background: var(--zc-bg);
      border: 1px solid var(--zc-border);
      display: flex; flex-direction: column;
      overflow: hidden;
      box-shadow: 0 8px 40px rgba(0,0,0,0.5);
      animation: slideUp 0.25s ease-out;
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* Header */
    .panel-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 14px 16px;
      border-bottom: 1px solid var(--zc-border);
      background: var(--zc-surface);
    }
    .header-brand { display: flex; align-items: center; gap: 10px; }
    .brand-icon {
      width: 30px; height: 30px; border-radius: 8px;
      background: color-mix(in srgb, var(--zc-primary) 16%, transparent);
      color: var(--zc-primary);
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 13px;
    }
    .header-title { font-weight: 600; font-size: 15px; }
    .close-btn {
      background: none; border: none; color: var(--zc-text-muted);
      cursor: pointer; padding: 4px; border-radius: 6px;
      transition: color 0.15s, background 0.15s;
    }
    .close-btn:hover { color: var(--zc-text); background: var(--zc-surface-alt); }

    /* Messages */
    .messages-area {
      flex: 1; overflow-y: auto; padding: 16px;
      display: flex; flex-direction: column; gap: 12px;
      scrollbar-width: thin;
      scrollbar-color: color-mix(in srgb, var(--zc-border) 85%, transparent) transparent;
    }

    .status-message {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 10px; text-align: center; color: var(--zc-text-muted);
      padding: 24px 8px; font-size: 13px;
    }
    .error-text { color: var(--zc-error); }

    .retry-btn {
      padding: 6px 16px; border-radius: 8px;
      background: var(--zc-surface); border: 1px solid var(--zc-border);
      color: var(--zc-text); cursor: pointer; font-size: 13px;
      transition: background 0.15s;
    }
    .retry-btn:hover { background: var(--zc-surface-alt); }

    .msg {
      padding: 10px 12px; border-radius: 10px;
      font-size: 13px; max-width: 92%;
    }
    .msg-user {
      align-self: flex-end;
      background: var(--zc-primary); color: #ffffff;
      border-bottom-right-radius: 4px;
    }
    .msg-assistant {
      align-self: flex-start;
      background: var(--zc-surface); border: 1px solid var(--zc-border);
      border-bottom-left-radius: 4px;
    }
    .msg-role {
      font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em;
      opacity: 0.6; margin-bottom: 4px; font-weight: 600;
    }
    .msg-content { white-space: pre-wrap; word-break: break-word; }

    /* Thinking dots */
    .thinking { display: flex; gap: 4px; padding: 4px 0; }
    .dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: var(--zc-text-muted);
      animation: bounce 1.4s infinite ease-in-out both;
    }
    .dot:nth-child(1) { animation-delay: -0.32s; }
    .dot:nth-child(2) { animation-delay: -0.16s; }
    @keyframes bounce {
      0%, 80%, 100% { transform: scale(0); }
      40% { transform: scale(1); }
    }

    /* Citations */
    .citations {
      margin-top: 8px; padding: 8px 10px;
      background: color-mix(in srgb, var(--zc-bg) 55%, transparent);
      border-radius: 8px;
      border: 1px solid var(--zc-border);
    }
    .citations-label {
      font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em;
      color: var(--zc-primary); font-weight: 600; margin-bottom: 6px;
    }
    .citation { margin-bottom: 6px; }
    .citation:last-child { margin-bottom: 0; }
    .citation-doc { font-size: 12px; font-weight: 600; color: var(--zc-text); }
    .citation-pages { font-size: 11px; color: var(--zc-text-muted); }
    .citation-excerpt { font-size: 11px; color: var(--zc-text-dim); margin-top: 2px; }

    /* Input */
    .input-area {
      padding: 12px 16px 10px;
      border-top: 1px solid var(--zc-border);
      background: var(--zc-surface);
    }
    .chat-error {
      padding: 6px 10px; margin-bottom: 8px; border-radius: 6px;
      font-size: 12px; color: var(--zc-error);
      background: var(--zc-error-bg); border: 1px solid color-mix(in srgb, var(--zc-error) 40%, transparent);
    }
    .input-row { display: flex; gap: 8px; align-items: flex-end; }
    .msg-input {
      flex: 1; resize: none;
      padding: 8px 12px; border-radius: 8px;
      border: 1px solid var(--zc-border);
      background: var(--zc-bg); color: var(--zc-text);
      font-family: inherit; font-size: 13px; line-height: 1.4;
      outline: none; transition: border-color 0.15s;
    }
    .msg-input::placeholder { color: var(--zc-text-dim); }
    .msg-input:focus { border-color: var(--zc-primary); }
    .msg-input:disabled { opacity: 0.5; }

    .send-btn {
      width: 38px; height: 38px; border-radius: 8px;
      border: none; background: var(--zc-primary); color: #ffffff;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: background 0.15s, opacity 0.15s; flex-shrink: 0;
    }
    .send-btn:hover:not(:disabled) { background: var(--zc-primary-hover); }
    .send-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    .powered-by {
      text-align: center; font-size: 10px; color: var(--zc-text-dim);
      margin-top: 8px;
    }
    .powered-by strong { color: var(--zc-text-muted); }

    /* Spinner */
    .spinner {
      width: 24px; height: 24px;
      border: 2px solid var(--zc-border);
      border-top-color: var(--zc-primary);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  `],
})
export class ZetesChatComponent implements OnInit, OnChanges, OnDestroy {
  @Input({ alias: 'widget-key' }) widgetKey = '';
  @Input({ alias: 'widget-secret' }) widgetSecret = '';
  @Input({ alias: 'api-base-url' }) apiBaseUrl = '';
  @Input({ alias: 'widget-theme' }) widgetTheme: WidgetTheme | string = 'auto';

  protected readonly api = inject(WidgetApiService);
  private readonly elRef = inject(ElementRef);

  protected readonly panelOpen = signal(false);
  protected readonly isCreatingSession = signal(false);
  protected readonly sessionError = signal('');
  protected readonly messages = signal<ChatMessage[]>([]);
  protected readonly isSending = signal(false);
  protected readonly chatError = signal('');
  protected draft = '';
  protected activeTheme: Exclude<WidgetTheme, 'auto'> = 'dark';

  private mediaQueryList?: MediaQueryList;
  private readonly onSystemThemeChange = (event: MediaQueryListEvent): void => {
    if (this.normalizeWidgetTheme() === 'auto') {
      this.activeTheme = event.matches ? 'light' : 'dark';
    }
  };

  ngOnInit(): void {
    this.api.configure(this.apiBaseUrl, this.widgetKey, this.widgetSecret);
    this.resolveTheme();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['widgetTheme']) {
      this.resolveTheme();
    }
  }

  ngOnDestroy(): void {
    this.unregisterSystemThemeListener();
  }

  protected openPanel(): void {
    this.panelOpen.set(true);

    if (!this.api.hasSession) {
      this.createSession();
    }
  }

  protected closePanel(): void {
    this.panelOpen.set(false);
  }

  protected retrySession(): void {
    this.createSession();
  }

  protected onEnterKey(event: Event): void {
    const keyboardEvent = event as KeyboardEvent;
    if (!keyboardEvent.shiftKey) {
      keyboardEvent.preventDefault();
      this.send();
    }
  }

   protected send(): void {
     const text = this.draft.trim();
     if (!text || !this.api.hasSession || this.isSending()) {
       return;
     }

     this.isSending.set(true);
     this.chatError.set('');

     // Optimistic update: show user message immediately with temporary negative ID
     const optimisticUserMessage: ChatMessage = {
       id: -Date.now(), // Temporary ID
       role: 'user',
       content: text,
     };
     this.messages.update((msgs) => [...msgs, optimisticUserMessage]);
     this.draft = '';
     this.scrollToBottom();

     this.api.sendMessage(text).subscribe({
       next: (data) => {
         // Replace optimistic message with real API responses
         this.messages.update((msgs) =>
           msgs.filter((m) => m.id !== optimisticUserMessage.id)
         );
         this.messages.update((msgs) => [
           ...msgs,
           data.user_message,
           { ...data.assistant_message, citations: data.citations },
         ]);
         this.scrollToBottom();
       },
       error: (err) => {
         this.chatError.set(err?.error?.message ?? 'Failed to send message. Please try again.');
         this.isSending.set(false);
       },
       complete: () => this.isSending.set(false),
     });
   }

  private createSession(): void {
    this.isCreatingSession.set(true);
    this.sessionError.set('');

    this.api.createSession().subscribe({
      next: () => {},
      error: (err) => {
        this.sessionError.set(err?.error?.message ?? 'Unable to start chat. Please try again.');
        this.isCreatingSession.set(false);
      },
      complete: () => this.isCreatingSession.set(false),
    });
  }

  private resolveTheme(): void {
    const requestedTheme = this.normalizeWidgetTheme();

    if (requestedTheme === 'auto') {
      this.registerSystemThemeListener();
      this.activeTheme = this.getSystemTheme();
      return;
    }

    this.unregisterSystemThemeListener();
    this.activeTheme = requestedTheme;
  }

  private normalizeWidgetTheme(): WidgetTheme {
    const value = this.readWidgetThemeValue();
    return value === 'light' || value === 'dark' || value === 'auto' ? value : 'auto';
  }

  private readWidgetThemeValue(): string {
    const inputValue = String(this.widgetTheme ?? '').trim().toLowerCase();
    const hostAttrValue = String(this.elRef.nativeElement.getAttribute('widget-theme') ?? '').trim().toLowerCase();

    // Prefer explicit input values, but if input is still default/empty, use host attribute.
    if (inputValue && inputValue !== 'auto') {
      return inputValue;
    }

    return hostAttrValue || inputValue || 'auto';
  }

  private getSystemTheme(): 'dark' | 'light' {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return 'dark';
    }

    return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  }

  private registerSystemThemeListener(): void {
    if (typeof window === 'undefined' || !window.matchMedia) {
      return;
    }

    if (!this.mediaQueryList) {
      this.mediaQueryList = window.matchMedia('(prefers-color-scheme: light)');
      this.mediaQueryList.addEventListener('change', this.onSystemThemeChange);
    }
  }

  private unregisterSystemThemeListener(): void {
    if (!this.mediaQueryList) {
      return;
    }

    this.mediaQueryList.removeEventListener('change', this.onSystemThemeChange);
    this.mediaQueryList = undefined;
  }

  private scrollToBottom(): void {
    setTimeout(() => {
      const shadow = this.elRef.nativeElement.shadowRoot;
      if (shadow) {
        const area = shadow.querySelector('.messages-area');
        if (area) {
          area.scrollTop = area.scrollHeight;
        }
      }
    }, 50);
  }
}


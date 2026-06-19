import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, tap } from 'rxjs';

export interface Citation {
  chunk_id?: number;
  document_name?: string;
  page_from?: number | null;
  page_to?: number | null;
  excerpt?: string;
  score?: number | null;
}

export interface ChatMessage {
  id: number;
  role: 'user' | 'assistant' | string;
  content: string;
  citations?: Citation[];
  created_at?: string;
}

export interface WidgetSettings {
  title: string;
  welcome_message: string;
  input_placeholder: string;
  primary_color: string;
  position: 'bottom-right' | 'bottom-left' | string;
  theme: 'auto' | 'light' | 'dark' | string;
  language: string;
  show_citations: boolean;
  allowed_domains: string[];
  suggested_questions: string[];
}

interface CreateSessionResponse {
  data: { id: number; title?: string };
  session_token: string;
}

interface SendMessageResponse {
  data: {
    chat_session_id: number;
    user_message: ChatMessage;
    assistant_message: ChatMessage;
    citations: Citation[];
  };
}

interface WidgetSettingsResponse {
  data: WidgetSettings;
}

@Injectable()
export class WidgetApiService {
  private readonly http = inject(HttpClient);

  private apiBaseUrl = '';
  private widgetKey = '';
  private widgetSecret = '';
  private userToken = '';
  private userEmail = '';
  private sessionToken = '';
  private chatSessionId: number | null = null;

  configure(apiBaseUrl: string, widgetKey: string, widgetSecret: string, options?: { userToken?: string; userEmail?: string }): void {
    this.apiBaseUrl = apiBaseUrl.replace(/\/+$/, '');
    this.widgetKey = widgetKey;
    this.widgetSecret = widgetSecret;
    this.userToken = options?.userToken?.trim() ?? '';
    this.userEmail = options?.userEmail?.trim() ?? '';
  }

  get hasSession(): boolean {
    return this.chatSessionId !== null && this.sessionToken !== '';
  }

  getSettings(): Observable<WidgetSettings> {
    return this.http
      .get<WidgetSettingsResponse>(`${this.apiBaseUrl}/api/widget/${this.widgetKey}/settings`)
      .pipe(map((res) => res.data));
  }

  createSession(title?: string): Observable<number> {
    return this.http
      .post<CreateSessionResponse>(
        `${this.apiBaseUrl}/api/widget/${this.widgetKey}/chats`,
        {
          title: title ?? 'Widget chat',
          ...(this.userToken ? { user_token: this.userToken } : {}),
          ...(this.userEmail ? { user_email: this.userEmail } : {}),
        },
        { headers: this.buildHeaders() }
      )
      .pipe(
        tap((res) => {
          this.chatSessionId = res.data.id;
          this.sessionToken = res.session_token;
        }),
        map((res) => res.data.id)
      );
  }

  sendMessage(message: string): Observable<SendMessageResponse['data']> {
    return this.http
      .post<SendMessageResponse>(
        `${this.apiBaseUrl}/api/widget/${this.widgetKey}/chats/message`,
        {
          chat_session_id: this.chatSessionId,
          message,
          session_token: this.sessionToken,
          ...(this.userToken ? { user_token: this.userToken } : {}),
          ...(this.userEmail ? { user_email: this.userEmail } : {}),
        },
        { headers: this.buildHeaders() }
      )
      .pipe(map((res) => res.data));
  }

  submitFeedback(messageId: number, rating: 'helpful' | 'unhelpful'): Observable<unknown> {
    return this.http.post(
      `${this.apiBaseUrl}/api/widget/${this.widgetKey}/feedback`,
      {
        chat_session_id: this.chatSessionId,
        chat_message_id: messageId,
        session_token: this.sessionToken,
        rating,
      },
      { headers: this.buildHeaders() }
    );
  }

  private buildHeaders(): HttpHeaders {
    return new HttpHeaders({
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Widget-Secret': this.widgetSecret,
    });
  }
}


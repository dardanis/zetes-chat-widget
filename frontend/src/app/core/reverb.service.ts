import { Injectable } from '@angular/core';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ChatMessage, Citation } from './rag-api.service';

export interface RealtimeChatEventPayload {
  type?: string;
  project_id?: number;
  chat_session_id?: number;
  message?: ChatMessage | null;
  user_message?: ChatMessage | null;
  assistant_message?: ChatMessage | null;
  data?: ChatMessage | null;
  citations?: Citation[];
  [key: string]: unknown;
}

export interface ReverbRuntimeConfig {
  appKey?: string;
  host?: string;
  port?: number;
  scheme?: 'http' | 'https';
  authEndpoint?: string;
}

export interface ChatChannelSubscription {
  leave: () => void;
}

interface ReverbResolvedConfig {
  appKey: string;
  host: string;
  port: number;
  scheme: 'http' | 'https';
  authEndpoint: string;
}

interface ChatEventHandlers {
  onMessage: (payload: RealtimeChatEventPayload) => void;
  onConnected?: () => void;
  onError?: (message: string) => void;
}

@Injectable({ providedIn: 'root' })
export class ReverbService {
  private echo: any | null = null;
  private configPromise: Promise<ReverbResolvedConfig> | null = null;
  private currentConfig: ReverbResolvedConfig | null = null;

  async subscribeToProjectChat(projectId: number, chatSessionId: number, handlers: ChatEventHandlers): Promise<ChatChannelSubscription | null> {
    const config = await this.loadConfig();

    if (!config.appKey) {
      return null;
    }

    const echo = this.getEcho(config);
    const channelName = this.buildChannelName(projectId, chatSessionId);
    const channel = echo.private(channelName) as any;

    channel.subscribed?.(() => handlers.onConnected?.());
    channel.error?.((error: unknown) => handlers.onError?.(this.describeError(error)));

    const eventNames = [
      'ChatMessageBroadcasted',
      'MessageBroadcasted',
      'MessageCreated',
      '.chat.message',
      '.message.created',
      '.chat.message.created',
    ];

    eventNames.forEach((eventName) => {
      channel.listen(eventName, (payload: RealtimeChatEventPayload) => {
        handlers.onMessage(payload);
      });
    });

    return {
      leave: () => {
        echo.leave(channelName);
      },
    };
  }

  leaveProjectChat(projectId: number, chatSessionId: number): void {
    this.echo?.leave(this.buildChannelName(projectId, chatSessionId));
  }

  private getEcho(config: ReverbResolvedConfig): any {
    if (this.echo && this.currentConfig && this.sameConfig(this.currentConfig, config)) {
      return this.echo;
    }

    if (typeof window !== 'undefined') {
      (window as unknown as { Pusher?: typeof Pusher }).Pusher = Pusher;
    }

    this.currentConfig = config;
    this.echo = new Echo({
      broadcaster: 'pusher',
      key: config.appKey,
      wsHost: config.host,
      wsPort: config.port,
      wssPort: config.port,
      forceTLS: config.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      cluster: 'mt1',
      authEndpoint: config.authEndpoint,
      authorizer: (channel: any) => ({
        authorize: (socketId: string, callback: (error: Error | null, data: unknown) => void) => {
          void this.authorizeChannel(channel.name, socketId, callback, config.authEndpoint);
        },
      }),
    });

    return this.echo;
  }

  private async authorizeChannel(channelName: string, socketId: string, callback: (error: Error | null, data: unknown) => void, authEndpoint: string): Promise<void> {
    try {
      const csrfToken = this.readCookie('ZETES-XSRF-TOKEN') ?? this.readCookie('XSRF-TOKEN') ?? '';
      const response = await fetch(authEndpoint, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Socket-Id': socketId,
          ...(csrfToken ? {
            'X-XSRF-TOKEN': csrfToken,
            'X-CSRF-TOKEN': csrfToken,
          } : {}),
        },
        body: JSON.stringify({
          socket_id: socketId,
          channel_name: channelName,
        }),
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        callback(new Error((data as { message?: string })?.message ?? 'Broadcast auth failed.'), data);
        return;
      }

      callback(null, data);
    } catch (error) {
      callback(new Error(this.describeError(error)), null);
    }
  }

  private async loadConfig(): Promise<ReverbResolvedConfig> {
    if (!this.configPromise) {
      this.configPromise = this.resolveConfig();
    }

    return this.configPromise;
  }

  private async resolveConfig(): Promise<ReverbResolvedConfig> {
    const runtimeConfig = await this.loadRuntimeConfig();
    const reverbConfig = this.extractReverbConfig(runtimeConfig);
    const scheme: 'http' | 'https' = reverbConfig.scheme ?? this.getDefaultScheme();
    const host = reverbConfig.host?.trim() || this.getDefaultHost();
    const port = Number(reverbConfig.port ?? this.getDefaultPort());

    return {
      appKey: reverbConfig.appKey?.trim() ?? '',
      host,
      port,
      scheme,
      authEndpoint: reverbConfig.authEndpoint?.trim() || '/broadcasting/auth',
    };
  }

  private async loadRuntimeConfig(): Promise<ReverbRuntimeConfig> {
    if (typeof document === 'undefined') {
      return {};
    }

    try {
      const runtimeConfigUrl = new URL('runtime-config.json', document.baseURI).toString();
      const response = await fetch(runtimeConfigUrl, { cache: 'no-store' });

      if (!response.ok) {
        return {};
      }

      return (await response.json()) as ReverbRuntimeConfig;
    } catch {
      return {};
    }
  }

  private buildChannelName(projectId: number, chatSessionId: number): string {
    return `project.${projectId}.chat.${chatSessionId}`;
  }

  private extractReverbConfig(runtimeConfig: ReverbRuntimeConfig & { reverb?: ReverbRuntimeConfig }): ReverbRuntimeConfig {
    return runtimeConfig.reverb ?? runtimeConfig;
  }

  private getDefaultHost(): string {
    if (typeof window === 'undefined') {
      return '127.0.0.1';
    }

    return window.location.hostname || '127.0.0.1';
  }

  private getDefaultPort(): number {
    return 8081;
  }

  private getDefaultScheme(): 'http' | 'https' {
    if (typeof window === 'undefined') {
      return 'http';
    }

    return window.location.protocol === 'https:' ? 'https' : 'http';
  }

  private sameConfig(left: ReverbResolvedConfig, right: ReverbResolvedConfig): boolean {
    return left.appKey === right.appKey
      && left.host === right.host
      && left.port === right.port
      && left.scheme === right.scheme
      && left.authEndpoint === right.authEndpoint;
  }

  private readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
      return null;
    }

    const cookie = document.cookie
      .split('; ')
      .find((part) => part.startsWith(`${name}=`));

    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : null;
  }

  private describeError(error: unknown): string {
    if (error instanceof Error) {
      return error.message;
    }

    if (typeof error === 'string') {
      return error;
    }

    return 'Unable to connect to realtime updates.';
  }
}





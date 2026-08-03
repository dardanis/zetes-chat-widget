import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, switchMap } from 'rxjs';
import { AuthService } from './auth.service';

export interface Tenant {
  id: number;
  name: string;
  country_code: string;
  status: 'active' | 'inactive' | string;
  created_at?: string;
  country?: Country;
}

export interface Project {
  id: number;
  tenant_id: number;
  country_code: string;
  owner_id: number;
  name: string;
  slug: string;
  widget_key: string;
  widget_secret?: string | null;
  status: 'active' | 'inactive' | string;
  created_at?: string;
  updated_at?: string;
  tenant?: Tenant;
  country?: Country;
  widget_settings?: WidgetSettings;
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
  show_project_selector: boolean;
  allowed_domains: string[];
  suggested_questions: string[];
  project_name?: string;
  project_documents?: Array<{
    id: number;
    name: string;
  }>;
}

export interface VoiceSettings {
  enabled: boolean;
  greeting: string;
  tts_voice: string;
  language: string;
  speech_timeout: string;
  max_turns: number;
  thinking_message: string;
  no_input_prompt: string;
  fallback_message: string;
  goodbye_message: string;
  unavailable_message: string;
  record_calls: boolean;
  transfer_number: string | null;
  phone_number?: string | null;
  twilio_phone_sid?: string | null;
  project_name?: string;
  twilio_configured?: boolean;
  webhook_url?: string;
}

export interface PhoneCall {
  id: number;
  tenant_id: number;
  project_id: number;
  chat_session_id: number | null;
  call_sid: string;
  from_number: string;
  to_number: string;
  from_country?: string | null;
  from_city?: string | null;
  status: string;
  direction: string;
  turn_count: number;
  duration_seconds?: number | null;
  recording_url?: string | null;
  started_at?: string | null;
  ended_at?: string | null;
  created_at?: string;
}

export interface PhoneCaller {
  from_number: string;
  from_country?: string | null;
  call_count: number;
  total_seconds: number;
  first_call_at?: string | null;
  last_call_at?: string | null;
}

export interface Country {
  code: string;
  name: string;
  status: 'active' | 'inactive' | string;
}

export interface ManagedUser {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'manager' | string;
  status: 'active' | 'inactive' | string;
  country_codes: string[];
  countries?: Country[];
}

// ...existing interfaces...

export interface DashboardStats {
  total_tenants: number;
  total_projects: number;
  total_documents: number;
  total_chats: number;
  documents_by_status: Record<string, number>;
  recent_projects: Project[];
}

export interface ProjectDocument {
  id: number;
  tenant_id: number;
  project_id: number;
  original_name: string;
  status: string;
  ingestion_type?: 'pdf' | 'web' | string;
  source_url?: string | null;
  metadata?: {
    pages_count?: number;
    chunks_count?: number;
    title?: string;
    provider?: string;
    space_key?: string;
    space_name?: string;
    external_page_id?: string;
    external_updated_at?: string;
    synced_external_updated_at?: string;
    latest_external_updated_at?: string;
    synced_at?: string;
    queued_at?: string;
    failed_at?: string;
    last_status?: string;
    last_error?: string;
  } | null;
  processed_at?: string | null;
  created_at?: string;
}

export interface DocumentChunkPreview {
  id: number;
  chunk_index: number;
  page_from?: number | null;
  page_to?: number | null;
  content: string;
  metadata?: Record<string, unknown> | null;
}

export interface CrawledUrl {
  id: number;
  url: string;
  title?: string | null;
  status: string;
  chunks_count?: number;
  processed_at?: string | null;
  updated_at?: string | null;
}

export interface AtlassianConnection {
  id: number;
  tenant_id: number;
  created_by: number;
  base_url: string;
  email: string;
  cloud_id?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface ConfluenceSpace {
  id?: string | null;
  key: string;
  name: string;
  type?: string;
}

export interface ProjectConfluenceSpace {
  id: number;
  tenant_id: number;
  project_id: number;
  atlassian_connection_id: number;
  space_id?: string | null;
  space_key: string;
  space_name: string;
  space_type: string;
  is_enabled: boolean;
  last_synced_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

export interface ChatSession {
  id: number;
  tenant_id: number;
  project_id: number;
  title?: string | null;
  channel: string;
  metadata?: {
    widget_session_created_at?: string | null;
    widget_last_request_at?: string | null;
    widget_user?: {
      token_present?: boolean;
      token_source?: string | null;
      email?: string | null;
      name?: string | null;
      subject?: string | null;
    } | null;
    caller?: {
      number?: string | null;
      country?: string | null;
      city?: string | null;
    } | null;
    call_sid?: string | null;
  } | null;
  created_at?: string;
  updated_at?: string;
}

export interface Citation {
  id?: number;
  chunk_id?: number;
  document_name?: string;
  page_from?: number | null;
  page_to?: number | null;
  excerpt?: string;
  score?: number | null;
  metadata?: {
    document_name?: string;
    page_from?: number | null;
    page_to?: number | null;
    excerpt?: string;
  };
}

export interface ChatMessage {
  id: number;
  role: 'user' | 'assistant' | string;
  content: string;
  citations?: Citation[];
  created_at?: string;
}

export interface ProjectAnalytics {
  total_chats: number;
  most_asked_questions: { question: string; count: number }[];
  failed_no_answer_questions: { question: string; answer: string; created_at?: string }[];
  average_response_time_seconds: number | null;
  top_referenced_documents: { document_name: string; count: number }[];
  feedback_score: {
    helpful: number;
    unhelpful: number;
    total: number;
    positive_rate: number | null;
  };
  chats_by_channel: Record<string, number>;
  voice: {
    total_calls: number;
    completed_calls: number;
    completion_rate: number | null;
    average_call_duration_seconds: number | null;
    average_turns_per_call: number | null;
    total_minutes: number;
    unique_callers: number;
    top_calling_numbers: { from_number: string; count: number }[];
    calls_by_day: { date: string; count: number }[];
  };
}

interface ApiListResponse<T> {
  data: T[];
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface ApiPaginatedResponse<T> {
  data: T[];
  meta: PaginationMeta;
}

interface ApiItemResponse<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class RagApiService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  listCountries(): Observable<ApiListResponse<Country>> {
    return this.http.get<ApiListResponse<Country>>('/api/countries');
  }

  listUsers(): Observable<ApiListResponse<ManagedUser>> {
    return this.http.get<ApiListResponse<ManagedUser>>('/api/admin/users');
  }

  createUser(payload: { name: string; email: string; password: string; role: string; status: string; country_codes: string[] }): Observable<ApiItemResponse<ManagedUser>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<ManagedUser>>('/api/admin/users', payload))
    );
  }

  updateUser(id: number, payload: Partial<{ name: string; email: string; role: string; status: string; country_codes: string[] }>): Observable<ApiItemResponse<ManagedUser>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<ManagedUser>>(`/api/admin/users/${id}`, payload))
    );
  }

  deleteUser(id: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/admin/users/${id}`))
    );
  }

  changeOwnPassword(payload: { current_password: string; password: string; password_confirmation: string }): Observable<{ message: string }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ message: string }>('/api/account/password', payload))
    );
  }

  listTenants(options?: { search?: string }): Observable<ApiListResponse<Tenant>> {
    return this.http.get<ApiListResponse<Tenant>>('/api/tenants', {
      params: this.compactParams({ search: options?.search }),
    });
  }

  createTenant(payload: { name: string; country_code: string; status?: string }): Observable<ApiItemResponse<Tenant>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<Tenant>>('/api/tenants', payload))
    );
  }

  updateTenant(id: number, payload: { name: string; country_code: string; status?: string }): Observable<ApiItemResponse<Tenant>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<Tenant>>(`/api/tenants/${id}`, payload))
    );
  }

  deleteTenant(id: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/tenants/${id}`))
    );
  }

  listProjects(options?: { search?: string }): Observable<ApiListResponse<Project>> {
    return this.http.get<ApiListResponse<Project>>('/api/projects', {
      params: this.compactParams({ search: options?.search }),
    });
  }

  createProject(payload: { tenant_id: number; name: string; country_code: string; status?: string }): Observable<ApiItemResponse<Project>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<Project>>('/api/projects', payload))
    );
  }

  updateProject(id: number, payload: { name: string; country_code: string; status?: string }): Observable<ApiItemResponse<Project>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<Project>>(`/api/projects/${id}`, payload))
    );
  }

  getWidgetSettings(projectId: number): Observable<ApiItemResponse<WidgetSettings>> {
    return this.http.get<ApiItemResponse<WidgetSettings>>(`/api/projects/${projectId}/widget-settings`);
  }

  updateWidgetSettings(projectId: number, payload: WidgetSettings): Observable<ApiItemResponse<WidgetSettings>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<WidgetSettings>>(`/api/projects/${projectId}/widget-settings`, payload))
    );
  }

  getVoiceSettings(projectId: number): Observable<ApiItemResponse<VoiceSettings>> {
    return this.http.get<ApiItemResponse<VoiceSettings>>(`/api/projects/${projectId}/voice-settings`);
  }

  updateVoiceSettings(projectId: number, payload: Partial<VoiceSettings>): Observable<ApiItemResponse<VoiceSettings>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<VoiceSettings>>(`/api/projects/${projectId}/voice-settings`, payload))
    );
  }

  assignPhoneNumber(projectId: number, phoneNumber: string): Observable<ApiItemResponse<VoiceSettings>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<VoiceSettings>>(`/api/projects/${projectId}/phone-number`, { phone_number: phoneNumber }))
    );
  }

  releasePhoneNumber(projectId: number): Observable<ApiItemResponse<VoiceSettings>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<ApiItemResponse<VoiceSettings>>(`/api/projects/${projectId}/phone-number`))
    );
  }

  listCalls(projectId: number, options?: { page?: number; per_page?: number; status?: string; from_number?: string }): Observable<ApiPaginatedResponse<PhoneCall>> {
    return this.http.get<ApiPaginatedResponse<PhoneCall>>(`/api/projects/${projectId}/calls`, {
      params: this.compactParams({
        page: options?.page ?? 1,
        per_page: options?.per_page ?? 15,
        status: options?.status,
        from_number: options?.from_number,
      }),
    });
  }

  listCallers(projectId: number): Observable<ApiListResponse<PhoneCaller>> {
    return this.http.get<ApiListResponse<PhoneCaller>>(`/api/projects/${projectId}/callers`);
  }

  deleteProject(id: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/projects/${id}`))
    );
  }

  getStats(): Observable<{ data: DashboardStats }> {
    return this.http.get<{ data: DashboardStats }>('/api/stats');
  }

  listDocuments(projectId: number, options?: { page?: number; per_page?: number }): Observable<ApiPaginatedResponse<ProjectDocument>> {
    return this.http.get<ApiPaginatedResponse<ProjectDocument>>(`/api/projects/${projectId}/documents`, {
      params: {
        page: String(options?.page ?? 1),
        per_page: String(options?.per_page ?? 10),
      },
    });
  }

  listDocumentContent(projectId: number, documentId: number, options?: { page?: number; per_page?: number }): Observable<ApiPaginatedResponse<DocumentChunkPreview>> {
    return this.http.get<ApiPaginatedResponse<DocumentChunkPreview>>(`/api/projects/${projectId}/documents/${documentId}/content`, {
      params: {
        page: String(options?.page ?? 1),
        per_page: String(options?.per_page ?? 25),
      },
    });
  }

  uploadDocument(projectId: number, file: File): Observable<ApiItemResponse<ProjectDocument>> {
    const formData = new FormData();
    formData.append('file', file);

    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<ProjectDocument>>(`/api/projects/${projectId}/documents`, formData))
    );
  }

  deleteDocument(projectId: number, documentId: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/projects/${projectId}/documents/${documentId}`))
    );
  }

  resyncConfluenceDocument(projectId: number, documentId: number): Observable<{ message: string; data: { document_id: number } }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ message: string; data: { document_id: number } }>(`/api/projects/${projectId}/documents/${documentId}/resync-confluence`, {}))
    );
  }

  crawlWebsite(projectId: number, payload: { url: string; max_pages?: number }): Observable<{ message: string; data: { url: string; max_pages: number } }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ message: string; data: { url: string; max_pages: number } }>(`/api/projects/${projectId}/crawl`, payload))
    );
  }

  listCrawledUrls(projectId: number, options?: { page?: number; per_page?: number }): Observable<ApiPaginatedResponse<CrawledUrl>> {
    return this.http.get<ApiPaginatedResponse<CrawledUrl>>(`/api/projects/${projectId}/crawled-urls`, {
      params: {
        page: String(options?.page ?? 1),
        per_page: String(options?.per_page ?? 10),
      },
    });
  }

  createConfluenceConnection(
    tenantId: number,
    payload: { base_url: string; email: string; api_token: string; cloud_id?: string | null }
  ): Observable<ApiItemResponse<AtlassianConnection>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<AtlassianConnection>>(`/api/tenants/${tenantId}/confluence/connections`, payload))
    );
  }

  listConfluenceConnections(tenantId: number): Observable<ApiListResponse<AtlassianConnection>> {
    return this.http.get<ApiListResponse<AtlassianConnection>>(`/api/tenants/${tenantId}/confluence/connections`);
  }

  listConfluenceSpaces(
    tenantId: number,
    connectionId: number,
    options?: { page?: number; per_page?: number; q?: string; all?: boolean }
  ): Observable<ApiPaginatedResponse<ConfluenceSpace>> {
    return this.http.get<ApiPaginatedResponse<ConfluenceSpace>>(`/api/tenants/${tenantId}/confluence/connections/${connectionId}/spaces`, {
      params: {
        page: String(options?.page ?? 1),
        per_page: String(options?.per_page ?? 10),
        q: options?.q ?? '',
        all: options?.all ? '1' : '0',
      },
    });
  }

  listProjectConfluenceSpaces(projectId: number): Observable<ApiListResponse<ProjectConfluenceSpace>> {
    return this.http.get<ApiListResponse<ProjectConfluenceSpace>>(`/api/projects/${projectId}/confluence/spaces`);
  }

  saveProjectConfluenceSpaces(
    projectId: number,
    payload: { connection_id: number; spaces: ConfluenceSpace[] }
  ): Observable<ApiListResponse<ProjectConfluenceSpace>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiListResponse<ProjectConfluenceSpace>>(`/api/projects/${projectId}/confluence/spaces`, payload))
    );
  }

  removeProjectConfluenceSpace(projectId: number, spaceId: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/projects/${projectId}/confluence/spaces/${spaceId}`))
    );
  }

  syncProjectConfluence(projectId: number, payload?: { connection_id?: number | null }): Observable<{ message: string; data: { spaces_queued: number } }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ message: string; data: { spaces_queued: number } }>(`/api/projects/${projectId}/confluence/sync`, payload ?? {}))
    );
  }

  listChats(projectId: number): Observable<ApiListResponse<ChatSession>> {
    return this.http.get<ApiListResponse<ChatSession>>(`/api/projects/${projectId}/chats`);
  }

  createChat(projectId: number, title: string): Observable<ApiItemResponse<ChatSession>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<ChatSession>>(`/api/projects/${projectId}/chats`, { title, channel: 'dashboard' }))
    );
  }

  getChatHistory(projectId: number, chatId: number): Observable<ApiListResponse<ChatMessage>> {
    return this.http.get<ApiListResponse<ChatMessage>>(`/api/projects/${projectId}/chats/${chatId}/history`);
  }

  submitDashboardFeedback(projectId: number, messageId: number, rating: 'helpful' | 'unhelpful'): Observable<ApiItemResponse<unknown>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<unknown>>(`/api/projects/${projectId}/messages/${messageId}/feedback`, { rating }))
    );
  }

  getProjectAnalytics(projectId: number): Observable<ApiItemResponse<ProjectAnalytics>> {
    return this.http.get<ApiItemResponse<ProjectAnalytics>>(`/api/projects/${projectId}/analytics`);
  }

  sendMessage(projectId: number, chatSessionId: number, message: string): Observable<{ data: { chat_session_id: number; user_message: ChatMessage; assistant_message: ChatMessage; citations: Citation[] } }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ data: { chat_session_id: number; user_message: ChatMessage; assistant_message: ChatMessage; citations: Citation[] } }>(`/api/projects/${projectId}/chats/message`, {
        chat_session_id: chatSessionId,
        message,
      }))
    );
  }

  private compactParams(params: Record<string, string | number | boolean | null | undefined>): Record<string, string> {
    return Object.fromEntries(
      Object.entries(params)
        .filter(([, value]) => value !== undefined && value !== null && String(value).trim() !== '')
        .map(([key, value]) => [key, String(value)])
    );
  }
}

import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, switchMap } from 'rxjs';
import { AuthService } from './auth.service';

export interface Tenant {
  id: number;
  name: string;
  created_at?: string;
}

export interface Project {
  id: number;
  tenant_id: number;
  owner_id: number;
  name: string;
  slug: string;
  widget_key: string;
  widget_secret?: string | null;
  created_at?: string;
  updated_at?: string;
  tenant?: Tenant;
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
  metadata?: { pages_count?: number; chunks_count?: number } | null;
  processed_at?: string | null;
  created_at?: string;
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

interface ApiListResponse<T> {
  data: T[];
}

interface ApiItemResponse<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class RagApiService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);

  listTenants(): Observable<ApiListResponse<Tenant>> {
    return this.http.get<ApiListResponse<Tenant>>('/api/tenants');
  }

  createTenant(name: string): Observable<ApiItemResponse<Tenant>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<Tenant>>('/api/tenants', { name }))
    );
  }

  updateTenant(id: number, name: string): Observable<ApiItemResponse<Tenant>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<Tenant>>(`/api/tenants/${id}`, { name }))
    );
  }

  deleteTenant(id: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/tenants/${id}`))
    );
  }

  listProjects(): Observable<ApiListResponse<Project>> {
    return this.http.get<ApiListResponse<Project>>('/api/projects');
  }

  createProject(payload: { tenant_id: number; name: string }): Observable<ApiItemResponse<Project>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<Project>>('/api/projects', payload))
    );
  }

  updateProject(id: number, name: string): Observable<ApiItemResponse<Project>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.put<ApiItemResponse<Project>>(`/api/projects/${id}`, { name }))
    );
  }

  deleteProject(id: number): Observable<void> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.delete<void>(`/api/projects/${id}`))
    );
  }

  getStats(): Observable<{ data: DashboardStats }> {
    return this.http.get<{ data: DashboardStats }>('/api/stats');
  }

  listDocuments(projectId: number): Observable<ApiListResponse<ProjectDocument>> {
    return this.http.get<ApiListResponse<ProjectDocument>>(`/api/projects/${projectId}/documents`);
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

  crawlWebsite(projectId: number, payload: { url: string; max_pages?: number }): Observable<{ message: string; data: { url: string; max_pages: number } }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ message: string; data: { url: string; max_pages: number } }>(`/api/projects/${projectId}/crawl`, payload))
    );
  }

  listCrawledUrls(projectId: number): Observable<ApiListResponse<CrawledUrl>> {
    return this.http.get<ApiListResponse<CrawledUrl>>(`/api/projects/${projectId}/crawled-urls`);
  }

  createConfluenceConnection(
    tenantId: number,
    payload: { base_url: string; email: string; api_token: string; cloud_id?: string | null }
  ): Observable<ApiItemResponse<AtlassianConnection>> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<ApiItemResponse<AtlassianConnection>>(`/api/tenants/${tenantId}/confluence/connections`, payload))
    );
  }

  listConfluenceSpaces(tenantId: number, connectionId: number): Observable<ApiListResponse<ConfluenceSpace>> {
    return this.http.get<ApiListResponse<ConfluenceSpace>>(`/api/tenants/${tenantId}/confluence/connections/${connectionId}/spaces`);
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

  sendMessage(projectId: number, chatSessionId: number, message: string): Observable<{ data: { chat_session_id: number; user_message: ChatMessage; assistant_message: ChatMessage; citations: Citation[] } }> {
    return this.auth.refreshCsrf().pipe(
      switchMap(() => this.http.post<{ data: { chat_session_id: number; user_message: ChatMessage; assistant_message: ChatMessage; citations: Citation[] } }>(`/api/projects/${projectId}/chats/message`, {
        chat_session_id: chatSessionId,
        message,
      }))
    );
  }
}



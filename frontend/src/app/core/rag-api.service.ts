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
  metadata?: { pages_count?: number; chunks_count?: number } | null;
  processed_at?: string | null;
  created_at?: string;
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



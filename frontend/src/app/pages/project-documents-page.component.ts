import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { CrawledUrl, ProjectConfluenceSpace, ConfluenceSpace, ProjectDocument, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-documents-page',
  standalone: true,
  imports: [FormsModule],
  template: `
    <section class="space-y-6">
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-2">
        <div class="grid gap-2 sm:grid-cols-3">
          <button
            type="button"
            (click)="activeIngestionTab.set('upload')"
            [class.bg-[var(--app-accent-soft)]]="activeIngestionTab() === 'upload'"
            [class.text-[var(--app-accent)]]="activeIngestionTab() === 'upload'"
            class="rounded-lg px-3 py-2 text-sm font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]"
          >
            Upload document
          </button>
          <button
            type="button"
            (click)="activeIngestionTab.set('crawler')"
            [class.bg-[var(--app-accent-soft)]]="activeIngestionTab() === 'crawler'"
            [class.text-[var(--app-accent)]]="activeIngestionTab() === 'crawler'"
            class="rounded-lg px-3 py-2 text-sm font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]"
          >
            Website crawler
          </button>
          <button
            type="button"
            (click)="activeIngestionTab.set('confluence')"
            [class.bg-[var(--app-accent-soft)]]="activeIngestionTab() === 'confluence'"
            [class.text-[var(--app-accent)]]="activeIngestionTab() === 'confluence'"
            class="rounded-lg px-3 py-2 text-sm font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]"
          >
            Confluence spaces
          </button>
        </div>
      </div>

      @if (activeIngestionTab() === 'upload') {
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">Upload document</h3>
        <p class="mt-1 text-xs text-[var(--app-text-muted)]">Files are queued for parsing, chunking, and embedding automatically.</p>

        @if (uploadError()) {
          <div class="mt-3 flex items-start gap-2 rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>{{ uploadError() }}</span>
          </div>
        }

        @if (deleteError()) {
          <div class="mt-3 flex items-start gap-2 rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>{{ deleteError() }}</span>
          </div>
        }

        <div
          class="relative mt-4 rounded-lg border-2 border-[var(--app-border)] border-dashed p-8 text-center transition"
          [class.border-cyan-400]="isDragging()"
          [class.bg-cyan-50]="isDragging()"
          (dragover)="onDragOver($event)"
          (dragleave)="isDragging.set(false)"
          (drop)="onDrop($event)"
        >
          <input id="file-upload" type="file" accept="application/pdf" (change)="onFileSelected($event)" class="absolute inset-0 cursor-pointer opacity-0" />
          <svg class="mx-auto h-8 w-8 text-[var(--app-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
          @if (selectedFile()) {
            <p class="mt-2 text-sm font-medium text-[var(--app-text)]">{{ selectedFile()!.name }}</p>
            <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ (selectedFile()!.size / 1024 / 1024).toFixed(1) }} MB</p>
          } @else {
            <p class="mt-2 text-sm text-[var(--app-text-muted)]">Drop a PDF here or <span class="text-[var(--app-accent)]">browse</span></p>
            <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">PDF files up to 50 MB</p>
          }
        </div>

        @if (selectedFile()) {
          <button type="button" (click)="uploadSelectedFile()" [disabled]="isUploading()" class="mt-4 rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
            {{ isUploading() ? 'Uploading...' : 'Upload PDF' }}
          </button>
        }
      </div>
      }

      @if (activeIngestionTab() === 'crawler') {
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">Website crawler</h3>
        <p class="mt-1 text-xs text-[var(--app-text-muted)]">Paste a URL to crawl and index same-domain pages automatically for this project.</p>

        @if (crawlError()) {
          <div class="mt-3 flex items-start gap-2 rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>{{ crawlError() }}</span>
          </div>
        }

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
          <input
            type="url"
            [(ngModel)]="crawlUrl"
            placeholder="https://example.com"
            class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
          />
          <button
            type="button"
            (click)="startWebsiteCrawl()"
            [disabled]="isCrawling() || !crawlUrl.trim()"
            class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
          >
            {{ isCrawling() ? 'Queueing...' : 'Crawl website' }}
          </button>
        </div>

        <div class="mt-4">
          <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Crawled URLs</p>
            <button type="button" (click)="loadCrawledUrls()" class="rounded-md px-2 py-1 text-xs text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]">Refresh</button>
          </div>

          @if (crawledUrls().length === 0) {
            <p class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-xs text-[var(--app-text-muted)]">No URLs crawled yet.</p>
          } @else {
            <div class="space-y-2">
              @for (item of crawledUrls(); track item.id) {
                <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2">
                  <p class="truncate text-xs font-medium text-[var(--app-text)]">{{ item.url }}</p>
                  <p class="mt-1 text-xs text-[var(--app-text-muted)]">{{ item.title || 'Untitled page' }} - {{ item.status }} - {{ item.chunks_count ?? 0 }} chunks</p>
                </div>
              }
            </div>
          }
        </div>
      </div>
      }

      @if (activeIngestionTab() === 'confluence') {
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">Confluence spaces</h3>
        <p class="mt-1 text-xs text-[var(--app-text-muted)]">Connect Atlassian, choose spaces for this project, and queue sync into your RAG index.</p>

        @if (confluenceError()) {
          <div class="mt-3 flex items-start gap-2 rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>{{ confluenceError() }}</span>
          </div>
        }

        @if (confluenceSuccess()) {
          <div class="mt-3 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-500">
            {{ confluenceSuccess() }}
          </div>
        }

        <div class="mt-4 grid gap-2 sm:grid-cols-2">
          <input
            type="url"
            [(ngModel)]="confluenceBaseUrl"
            placeholder="https://your-site.atlassian.net"
            class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
          />
          <input
            type="email"
            [(ngModel)]="confluenceEmail"
            placeholder="you@company.com"
            class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
          />
          <input
            type="password"
            [(ngModel)]="confluenceApiToken"
            placeholder="Atlassian API token"
            class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
          />
          <input
            type="text"
            [(ngModel)]="confluenceCloudId"
            placeholder="Cloud ID (optional)"
            class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
          />
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
          <button
            type="button"
            (click)="connectAndLoadConfluenceSpaces()"
            [disabled]="isConfluenceConnecting() || !canConnectConfluence()"
            class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
          >
            {{ isConfluenceConnecting() ? 'Connecting...' : 'Connect + load spaces' }}
          </button>

          <button
            type="button"
            (click)="loadConfluenceSpaces()"
            [disabled]="isConfluenceLoadingSpaces() || !confluenceConnectionId() || !projectTenantId()"
            class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)] disabled:opacity-60"
          >
            {{ isConfluenceLoadingSpaces() ? 'Loading...' : 'Refresh spaces' }}
          </button>
        </div>

        @if (availableConfluenceSpaces().length > 0) {
          <div class="mt-4 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3">
            <div class="mb-2 flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Available spaces</p>
              <span class="text-xs text-[var(--app-text-muted)]">{{ selectedSpaceKeys().size }} selected</span>
            </div>

            <div class="max-h-56 space-y-2 overflow-auto pr-1">
              @for (space of availableConfluenceSpaces(); track space.key) {
                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-transparent px-2 py-1.5 hover:border-[var(--app-border)] hover:bg-[var(--app-surface)]">
                  <input
                    type="checkbox"
                    class="mt-0.5"
                    [checked]="isSpaceSelected(space.key)"
                    (change)="toggleConfluenceSpace(space, $event)"
                  />
                  <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-[var(--app-text)]">{{ space.name }}</span>
                    <span class="block text-xs text-[var(--app-text-muted)]">{{ space.key }} - {{ space.type || 'global' }}</span>
                  </span>
                </label>
              }
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                (click)="saveSelectedConfluenceSpaces()"
                [disabled]="isConfluenceSavingSpaces() || !confluenceConnectionId()"
                class="rounded-lg bg-[var(--app-accent)] px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60"
              >
                {{ isConfluenceSavingSpaces() ? 'Saving...' : 'Save selected spaces' }}
              </button>

              <button
                type="button"
                (click)="syncConfluenceSpaces()"
                [disabled]="isConfluenceSyncing() || selectedProjectSpaces().length === 0"
                class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] disabled:opacity-60"
              >
                {{ isConfluenceSyncing() ? 'Queueing...' : 'Sync now' }}
              </button>
            </div>
          </div>
        }

        @if (selectedProjectSpaces().length > 0) {
          <div class="mt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Saved for this project</p>
            <div class="space-y-2">
              @for (space of selectedProjectSpaces(); track space.id) {
                <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2">
                  <p class="text-sm font-medium text-[var(--app-text)]">{{ space.space_name }}</p>
                  <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ space.space_key }} - {{ space.space_type }}</p>
                  @if (space.last_synced_at) {
                    <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">Last sync: {{ space.last_synced_at }}</p>
                  }
                </div>
              }
            </div>
          </div>
        }
      </div>
      }

      <div>
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-[var(--app-text)]">Documents</h3>
          <button type="button" (click)="loadDocuments()" class="rounded-md p-1.5 text-[var(--app-text-muted)] hover:bg-[var(--app-surface)] hover:text-[var(--app-text)]" aria-label="Refresh documents">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.727a.75.75 0 00-.75.75v3.505a.75.75 0 001.5 0v-1.995l.009.01a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm-1.768-7.908a.75.75 0 00-1.449-.39A5.5 5.5 0 013.894 5.592l-.312-.311h2.433a.75.75 0 000-1.5H2.51a.75.75 0 00-.75.75V8.04a.75.75 0 001.5 0V6.044l.009.01a7 7 0 0011.712-3.138.75.75 0 00-1.437-.4z" clip-rule="evenodd"/></svg>
          </button>
        </div>

        @if (isLoadingDocs()) {
          <div class="mt-3 space-y-2">
            <div class="h-16 animate-pulse rounded-lg bg-[var(--app-surface)]"></div>
            <div class="h-16 animate-pulse rounded-lg bg-[var(--app-surface)]"></div>
            <div class="h-16 animate-pulse rounded-lg bg-[var(--app-surface)]"></div>
          </div>
        } @else {
          <div class="mt-3 space-y-2">
            @for (document of documents(); track document.id) {
              <div class="flex items-center justify-between gap-3 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-4 py-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-[var(--app-text)]">{{ document.original_name }}</p>
                  @if (document.metadata?.chunks_count) {
                    <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ document.metadata?.chunks_count }} chunks - {{ document.metadata?.pages_count ?? 0 }} pages</p>
                  }
                </div>

                <div class="flex shrink-0 items-center gap-2">
                  @switch (document.status) {
                    @case ('completed') {
                      <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-400/10 px-2.5 py-1 text-xs font-medium text-emerald-500">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Indexed
                      </span>
                    }
                    @case ('processing') {
                      <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-400/10 px-2.5 py-1 text-xs font-medium text-amber-500">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                        Processing
                      </span>
                    }
                    @case ('pending') {
                      <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--app-accent-soft)] px-2.5 py-1 text-xs font-medium text-[var(--app-accent)]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--app-accent)]"></span>
                        Queued
                      </span>
                    }
                    @case ('failed') {
                      <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--app-danger)]/10 px-2.5 py-1 text-xs font-medium text-[var(--app-danger)]">
                        <span class="h-1.5 w-1.5 rounded-full bg-[var(--app-danger)]"></span>
                        Failed
                      </span>
                    }
                    @default {
                      <span class="inline-flex shrink-0 items-center rounded-full bg-[var(--app-surface-2)] px-2.5 py-1 text-xs font-medium text-[var(--app-text-muted)]">{{ document.status }}</span>
                    }
                  }

                  <button
                    type="button"
                    (click)="openDeleteModal(document)"
                    [disabled]="isDeletingDocument(document.id)"
                    class="rounded-md border border-[var(--app-border)] px-2.5 py-1 text-xs font-medium text-[var(--app-text-muted)] transition hover:border-[var(--app-danger)]/40 hover:bg-[var(--app-danger)]/10 hover:text-[var(--app-danger)] disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {{ isDeletingDocument(document.id) ? 'Deleting...' : 'Delete' }}
                  </button>
                </div>
              </div>
            } @empty {
              <div class="py-12 text-center">
                <svg class="mx-auto h-10 w-10 text-[var(--app-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                <p class="mt-3 text-sm text-[var(--app-text-muted)]">No documents uploaded yet</p>
                <p class="mt-1 text-xs text-[var(--app-text-muted)]">Upload a PDF above to get started.</p>
              </div>
            }
          </div>
        }
      </div>

      @if (pendingDeleteDocument()) {
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4" (click)="closeDeleteModal()">
          <div class="w-full max-w-md rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5 shadow-xl" (click)="$event.stopPropagation()">
            <h4 class="text-base font-semibold text-[var(--app-text)]">Delete document?</h4>
            <p class="mt-2 text-sm text-[var(--app-text-muted)]">
              This will permanently delete
              <span class="font-medium text-[var(--app-text)]">{{ pendingDeleteDocument()!.original_name }}</span>
              and its indexed chunks/citations.
            </p>

            <div class="mt-5 flex items-center justify-end gap-2">
              <button
                type="button"
                (click)="closeDeleteModal()"
                [disabled]="isDeletingDocument(pendingDeleteDocument()!.id)"
                class="rounded-md border border-[var(--app-border)] px-3 py-1.5 text-sm text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)] disabled:opacity-50"
              >
                Cancel
              </button>
              <button
                type="button"
                (click)="confirmDeleteDocument()"
                [disabled]="isDeletingDocument(pendingDeleteDocument()!.id)"
                class="rounded-md bg-[var(--app-danger)] px-3 py-1.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-50"
              >
                {{ isDeletingDocument(pendingDeleteDocument()!.id) ? 'Deleting...' : 'Delete document' }}
              </button>
            </div>
          </div>
        </div>
      }
    </section>
  `,
})
export class ProjectDocumentsPageComponent implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly api = inject(RagApiService);

  protected readonly documents = signal<ProjectDocument[]>([]);
  protected readonly selectedFile = signal<File | null>(null);
  protected readonly isUploading = signal(false);
  protected readonly isLoadingDocs = signal(true);
  protected readonly isDragging = signal(false);
  protected readonly uploadError = signal('');
  protected readonly deleteError = signal('');
  protected readonly deletingDocumentIds = signal<Set<number>>(new Set());
  protected readonly pendingDeleteDocument = signal<ProjectDocument | null>(null);
  protected readonly crawledUrls = signal<CrawledUrl[]>([]);
  protected readonly isCrawling = signal(false);
  protected readonly crawlError = signal('');
  protected crawlUrl = '';
  protected readonly activeIngestionTab = signal<'upload' | 'crawler' | 'confluence'>('upload');
  protected readonly projectTenantId = signal<number | null>(null);
  protected readonly confluenceConnectionId = signal<number | null>(null);
  protected readonly availableConfluenceSpaces = signal<ConfluenceSpace[]>([]);
  protected readonly selectedProjectSpaces = signal<ProjectConfluenceSpace[]>([]);
  protected readonly selectedSpaceKeys = signal<Set<string>>(new Set());
  protected readonly isConfluenceConnecting = signal(false);
  protected readonly isConfluenceLoadingSpaces = signal(false);
  protected readonly isConfluenceSavingSpaces = signal(false);
  protected readonly isConfluenceSyncing = signal(false);
  protected readonly confluenceError = signal('');
  protected readonly confluenceSuccess = signal('');
  protected confluenceBaseUrl = '';
  protected confluenceEmail = '';
  protected confluenceApiToken = '';
  protected confluenceCloudId = '';

  ngOnInit(): void {
    this.loadDocuments();
    this.loadCrawledUrls();
    this.loadConfluenceContext();
  }

  protected canConnectConfluence(): boolean {
    return Boolean(
      this.projectTenantId()
      && this.confluenceBaseUrl.trim()
      && this.confluenceEmail.trim()
      && this.confluenceApiToken.trim(),
    );
  }

  protected onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile.set(input.files?.item(0) ?? null);
  }

  protected onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.isDragging.set(true);
  }

  protected onDrop(event: DragEvent): void {
    event.preventDefault();
    this.isDragging.set(false);
    const file = event.dataTransfer?.files.item(0);
    if (file && file.type === 'application/pdf') {
      this.selectedFile.set(file);
    }
  }

  protected uploadSelectedFile(): void {
    const file = this.selectedFile();
    if (!file) {
      return;
    }

    this.isUploading.set(true);
    this.uploadError.set('');
    this.deleteError.set('');

    this.api.uploadDocument(this.requireProjectId(), file).subscribe({
      next: ({ data }) => {
        this.documents.update((documents) => [data, ...documents]);
        this.selectedFile.set(null);
      },
      error: (error: HttpErrorResponse) => {
        this.uploadError.set(error.error?.message ?? 'PDF upload failed.');
        this.isUploading.set(false);
      },
      complete: () => this.isUploading.set(false),
    });
  }

  protected loadDocuments(): void {
    this.isLoadingDocs.set(true);
    this.deleteError.set('');

    this.api.listDocuments(this.requireProjectId()).subscribe({
      next: ({ data }) => this.documents.set(data),
      error: () => {
        this.uploadError.set('Failed to load documents.');
        this.isLoadingDocs.set(false);
      },
      complete: () => this.isLoadingDocs.set(false),
    });
  }

  protected loadCrawledUrls(): void {
    this.api.listCrawledUrls(this.requireProjectId()).subscribe({
      next: ({ data }) => this.crawledUrls.set(data),
      error: () => this.crawlError.set('Failed to load crawled URLs.'),
    });
  }

  protected startWebsiteCrawl(): void {
    const url = this.crawlUrl.trim();

    if (!url) {
      return;
    }

    this.isCrawling.set(true);
    this.crawlError.set('');

    this.api.crawlWebsite(this.requireProjectId(), { url }).subscribe({
      next: () => {
        this.loadCrawledUrls();
        this.loadDocuments();
      },
      error: (error: HttpErrorResponse) => {
        this.crawlError.set(error.error?.message ?? 'Unable to start website crawl.');
      },
      complete: () => this.isCrawling.set(false),
    });
  }

  protected connectAndLoadConfluenceSpaces(): void {
    const tenantId = this.projectTenantId();

    if (!tenantId) {
      this.confluenceError.set('Project tenant was not resolved yet. Reload and try again.');

      return;
    }

    this.isConfluenceConnecting.set(true);
    this.confluenceError.set('');
    this.confluenceSuccess.set('');

    this.api.createConfluenceConnection(tenantId, {
      base_url: this.confluenceBaseUrl.trim(),
      email: this.confluenceEmail.trim(),
      api_token: this.confluenceApiToken.trim(),
      cloud_id: this.confluenceCloudId.trim() || null,
    }).subscribe({
      next: ({ data }) => {
        this.confluenceConnectionId.set(data.id);
        this.confluenceSuccess.set('Connection saved. Loading available spaces...');
        this.loadConfluenceSpaces();
      },
      error: (error: HttpErrorResponse) => {
        this.confluenceError.set(error.error?.message ?? 'Failed to create Confluence connection.');
      },
      complete: () => this.isConfluenceConnecting.set(false),
    });
  }

  protected loadConfluenceSpaces(): void {
    const tenantId = this.projectTenantId();
    const connectionId = this.confluenceConnectionId();

    if (!tenantId || !connectionId) {
      return;
    }

    this.isConfluenceLoadingSpaces.set(true);
    this.confluenceError.set('');

    this.api.listConfluenceSpaces(tenantId, connectionId).subscribe({
      next: ({ data }) => {
        this.availableConfluenceSpaces.set(data);
      },
      error: (error: HttpErrorResponse) => {
        this.confluenceError.set(error.error?.message ?? 'Failed to load Confluence spaces.');
      },
      complete: () => this.isConfluenceLoadingSpaces.set(false),
    });
  }

  protected isSpaceSelected(spaceKey: string): boolean {
    return this.selectedSpaceKeys().has(spaceKey);
  }

  protected toggleConfluenceSpace(space: ConfluenceSpace, event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;

    this.selectedSpaceKeys.update((keys) => {
      const next = new Set(keys);

      if (checked) {
        next.add(space.key);
      } else {
        next.delete(space.key);
      }

      return next;
    });
  }

  protected saveSelectedConfluenceSpaces(): void {
    const connectionId = this.confluenceConnectionId();

    if (!connectionId) {
      return;
    }

    const selectedKeys = this.selectedSpaceKeys();
    const spaces = this.availableConfluenceSpaces().filter((space) => selectedKeys.has(space.key));

    this.isConfluenceSavingSpaces.set(true);
    this.confluenceError.set('');
    this.confluenceSuccess.set('');

    this.api.saveProjectConfluenceSpaces(this.requireProjectId(), {
      connection_id: connectionId,
      spaces,
    }).subscribe({
      next: ({ data }) => {
        this.selectedProjectSpaces.set(data);
        this.confluenceSuccess.set('Selected Confluence spaces saved for this project.');
      },
      error: (error: HttpErrorResponse) => {
        this.confluenceError.set(error.error?.message ?? 'Failed to save selected spaces.');
      },
      complete: () => this.isConfluenceSavingSpaces.set(false),
    });
  }

  protected syncConfluenceSpaces(): void {
    this.isConfluenceSyncing.set(true);
    this.confluenceError.set('');
    this.confluenceSuccess.set('');

    const connectionId = this.confluenceConnectionId();
    const payload = connectionId ? { connection_id: connectionId } : undefined;

    this.api.syncProjectConfluence(this.requireProjectId(), payload).subscribe({
      next: ({ data }) => {
        this.confluenceSuccess.set(`Sync queued for ${data.spaces_queued} space(s).`);
        this.loadDocuments();
        this.loadProjectConfluenceSpaces();
      },
      error: (error: HttpErrorResponse) => {
        this.confluenceError.set(error.error?.message ?? 'Failed to queue Confluence sync.');
      },
      complete: () => this.isConfluenceSyncing.set(false),
    });
  }

  private loadConfluenceContext(): void {
    const projectId = this.requireProjectId();

    this.api.listProjects().subscribe({
      next: ({ data }) => {
        const project = data.find((item) => item.id === projectId);
        this.projectTenantId.set(project?.tenant_id ?? null);
      },
    });

    this.loadProjectConfluenceSpaces();
  }

  private loadProjectConfluenceSpaces(): void {
    this.api.listProjectConfluenceSpaces(this.requireProjectId()).subscribe({
      next: ({ data }) => {
        this.selectedProjectSpaces.set(data);

        if (!this.confluenceConnectionId() && data.length > 0) {
          this.confluenceConnectionId.set(data[0].atlassian_connection_id);
        }

        this.selectedSpaceKeys.set(new Set(data.map((item) => item.space_key)));
      },
    });
  }

  protected isDeletingDocument(documentId: number): boolean {
    return this.deletingDocumentIds().has(documentId);
  }

  protected openDeleteModal(document: ProjectDocument): void {
    this.pendingDeleteDocument.set(document);
  }

  protected closeDeleteModal(): void {
    this.pendingDeleteDocument.set(null);
  }

  protected confirmDeleteDocument(): void {
    const document = this.pendingDeleteDocument();
    if (!document) {
      return;
    }

    this.deleteError.set('');
    this.deletingDocumentIds.update((ids) => {
      const next = new Set(ids);
      next.add(document.id);

      return next;
    });

    this.api.deleteDocument(this.requireProjectId(), document.id).subscribe({
      next: () => {
        this.documents.update((documents) => documents.filter((current) => current.id !== document.id));
        this.pendingDeleteDocument.set(null);
      },
      error: (error: HttpErrorResponse) => {
        this.deleteError.set(error.error?.message ?? 'Failed to delete document.');
      },
      complete: () => {
        this.deletingDocumentIds.update((ids) => {
          const next = new Set(ids);
          next.delete(document.id);

          return next;
        });
      },
    });
  }

  private requireProjectId(): number {
    return Number(this.route.parent?.snapshot.paramMap.get('projectId'));
  }
}


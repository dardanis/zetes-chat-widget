import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { AtlassianConnection, CrawledUrl, DocumentChunkPreview, PaginationMeta, ProjectConfluenceSpace, ConfluenceSpace, ProjectDocument, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-documents-page',
  standalone: true,
  imports: [FormsModule, DatePipe],
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
          <div class="mb-2 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Crawled URLs ({{ crawledUrlsTotal() }})</p>

              <div class="flex items-center gap-2">
                <label class="text-xs text-[var(--app-text-muted)]">Page size</label>
                <select
                  [ngModel]="crawledUrlsPerPage()"
                  (ngModelChange)="setCrawledUrlsPerPage($event)"
                  class="rounded-md border border-[var(--app-border)] bg-[var(--app-surface)] px-2 py-1 text-xs text-[var(--app-text)]"
                >
                  @for (size of pageSizeOptions; track size) {
                    <option [ngValue]="size">{{ size }}</option>
                  }
                </select>

                <button type="button" (click)="loadCrawledUrls()" class="rounded-md px-2 py-1 text-xs text-[var(--app-text-muted)] hover:bg-[var(--app-surface)] hover:text-[var(--app-text)]">Refresh</button>
              </div>
            </div>
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

            @if (crawledUrlsTotalPages() > 1) {
              <div class="mt-3 flex items-center justify-between">
                <p class="text-xs text-[var(--app-text-muted)]">Page {{ crawledUrlsPage() }} of {{ crawledUrlsTotalPages() }}</p>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    (click)="goToCrawledUrlsPage(crawledUrlsPage() - 1)"
                    [disabled]="crawledUrlsPage() <= 1"
                    class="rounded-md border border-[var(--app-border)] px-2 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] disabled:opacity-50"
                  >
                    Prev
                  </button>
                  <button
                    type="button"
                    (click)="goToCrawledUrlsPage(crawledUrlsPage() + 1)"
                    [disabled]="crawledUrlsPage() >= crawledUrlsTotalPages()"
                    class="rounded-md border border-[var(--app-border)] px-2 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
              </div>
            }
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

        <div class="mt-4 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3">
          <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Existing connections</p>
            <button
              type="button"
              (click)="showConfluenceForm.set(!showConfluenceForm())"
              class="rounded-lg border border-[var(--app-border)] px-3 py-1.5 text-xs font-medium text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)]"
            >
              + New connection
            </button>
          </div>

          @if (confluenceConnections().length === 0) {
            <p class="mt-2 text-xs text-[var(--app-text-muted)]">No saved Confluence connections yet.</p>
          } @else {
            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
              <select
                class="min-w-0 flex-1 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
                [ngModel]="confluenceConnectionId()"
                (ngModelChange)="onConfluenceConnectionSelected($event)"
              >
                <option [ngValue]="null">Select a connection</option>
                @for (connection of confluenceConnections(); track connection.id) {
                  <option [ngValue]="connection.id">#{{ connection.id }} - {{ connection.email }} - {{ connection.base_url }}</option>
                }
              </select>

              <button
                type="button"
                (click)="loadConfluenceSpaces()"
                [disabled]="isConfluenceLoadingSpaces() || !confluenceConnectionId() || !projectTenantId()"
                class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] disabled:opacity-60"
              >
                {{ isConfluenceLoadingSpaces() ? 'Loading...' : 'Load spaces' }}
              </button>
            </div>
          }
        </div>

        @if (showConfluenceForm()) {
        <div class="mt-4 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-4">
          <h4 class="text-sm font-semibold text-[var(--app-text)]">Create connection</h4>
          <div class="mt-3 grid gap-2 sm:grid-cols-2">
            <input
              type="url"
              [(ngModel)]="confluenceBaseUrl"
              placeholder="https://your-site.atlassian.net"
              class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
            />
            <input
              type="email"
              [(ngModel)]="confluenceEmail"
              placeholder="you@company.com"
              class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
            />
            <input
              type="password"
              [(ngModel)]="confluenceApiToken"
              placeholder="Atlassian API token"
              class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
            />
            <input
              type="text"
              [(ngModel)]="confluenceCloudId"
              placeholder="Cloud ID (optional)"
              class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-sm text-[var(--app-text)] outline-none ring-[var(--app-accent)]/40 transition focus:ring-2"
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
              (click)="showConfluenceForm.set(false)"
              class="rounded-lg border border-[var(--app-border)] px-4 py-2 text-sm text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)]"
            >
              Cancel
            </button>
          </div>
        </div>
        }

        @if (confluenceConnectionId()) {
          <div class="mt-4 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3 shadow-sm">
            @if (isConfluenceLoadingSpaces() || availableConfluenceSpaces().length > 0) {
            <div class="mb-2 flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Available spaces</p>
              <span class="text-xs text-[var(--app-text-muted)]">{{ selectedSpaceKeys().size }} selected</span>
            </div>

            <div class="mb-2 flex gap-2 sm:items-center">
              <label class="inline-flex items-center gap-2 text-xs text-[var(--app-text-muted)]">
                <input
                  type="checkbox"
                  [checked]="areAllVisibleConfluenceSpacesSelected()"
                  (change)="toggleSelectAllMatchingConfluenceSpaces($event)"
                  [disabled]="isConfluenceSelectingAll()"
                />
                {{ isConfluenceSelectingAll() ? 'Selecting...' : 'Select all' }}
              </label>
            </div>
            }

            <div class="max-h-56 space-y-2 overflow-auto pr-1">
              @if (availableConfluenceSpaces().length === 0) {
                @if (isConfluenceLoadingSpaces()) {
                  <p class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-xs text-[var(--app-text-muted)]">Loading spaces...</p>
                } @else {
                  <p class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] px-3 py-2 text-xs text-[var(--app-text-muted)]">No spaces available.</p>
                }
              } @else {
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
            <div class="mb-2 flex items-center justify-between">
              <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Saved for this project</p>
              <div class="flex items-center gap-2">
                <label class="text-xs text-[var(--app-text-muted)]">Page size</label>
                <select
                  [ngModel]="confluenceSpacesPerPage()"
                  (ngModelChange)="setConfluenceSpacesPerPage($event)"
                  class="rounded-md border border-[var(--app-border)] bg-[var(--app-surface)] px-2 py-1 text-xs text-[var(--app-text)]"
                >
                  @for (size of pageSizeOptions; track size) {
                    <option [ngValue]="size">{{ size }}</option>
                  }
                </select>
              </div>
            </div>
            <div class="space-y-2">
              @for (space of selectedProjectSpaces(); track space.id) {
                <div class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-[var(--app-text)]">{{ space.space_name }}</p>
                      <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ space.space_key }} - {{ space.space_type }}</p>
                      @if (space.last_synced_at) {
                        <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">Last sync: {{ space.last_synced_at | date:'MMM d, y, h:mm a' }}</p>
                      }
                    </div>

                    <button
                      type="button"
                      (click)="removeProjectConfluenceSpace(space)"
                      [disabled]="isRemovingProjectSpace(space.id)"
                      class="shrink-0 rounded-md border border-[var(--app-border)] px-2.5 py-1 text-xs font-medium text-[var(--app-text-muted)] transition hover:border-[var(--app-danger)]/40 hover:bg-[var(--app-danger)]/10 hover:text-[var(--app-danger)] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                      {{ isRemovingProjectSpace(space.id) ? 'Removing...' : 'Remove' }}
                    </button>
                  </div>
                </div>
              }
            </div>
          </div>
        }
      </div>
      }

       <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
         <div class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-[var(--app-border)]/50 bg-[var(--app-surface-2)]/40 px-3 py-2">
           <span class="text-xs font-medium text-[var(--app-text-muted)]">Status:</span>
           <div class="flex flex-wrap gap-3">
             <div class="flex items-center gap-1.5">
               <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
               <span class="text-xs text-[var(--app-text-muted)]">Indexed</span>
             </div>
             <div class="flex items-center gap-1.5">
               <span class="h-2 w-2 rounded-full bg-amber-500"></span>
               <span class="text-xs text-[var(--app-text-muted)]">Queued</span>
             </div>
             <div class="flex items-center gap-1.5">
               <span class="h-2 w-2 rounded-full bg-[var(--app-danger)]"></span>
               <span class="text-xs text-[var(--app-text-muted)]">Error/Outdated</span>
             </div>
           </div>
         </div>

         <div class="mb-2 rounded-lg border border-[var(--app-border)] bg-[var(--app-surface)] p-3 shadow-sm">
           <div class="flex flex-wrap items-center justify-between gap-2">
             <h3 class="text-sm font-semibold text-[var(--app-text)]">Documents ({{ documentsTotal() }})</h3>
            <div class="flex items-center gap-2">
              <label class="text-xs text-[var(--app-text-muted)]">Page size</label>
              <select
                [ngModel]="documentsPerPage()"
                (ngModelChange)="setDocumentsPerPage($event)"
                class="rounded-md border border-[var(--app-border)] bg-[var(--app-surface-2)] px-2 py-1 text-xs text-[var(--app-text)]"
              >
                @for (size of pageSizeOptions; track size) {
                  <option [ngValue]="size">{{ size }}</option>
                }
              </select>

              <button type="button" (click)="loadDocuments()" class="rounded-md p-1.5 text-[var(--app-text-muted)] hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]" aria-label="Refresh documents">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H4.727a.75.75 0 00-.75.75v3.505a.75.75 0 001.5 0v-1.995l.009.01a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm-1.768-7.908a.75.75 0 00-1.449-.39A5.5 5.5 0 013.894 5.592l-.312-.311h2.433a.75.75 0 000-1.5H2.51a.75.75 0 00-.75.75V8.04a.75.75 0 001.5 0V6.044l.009.01a7 7 0 0011.712-3.138.75.75 0 00-1.437-.4z" clip-rule="evenodd"/></svg>
              </button>
            </div>
          </div>
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
              <div
                class="flex items-center justify-between gap-3 rounded-lg border bg-[var(--app-surface)] px-4 py-3"
                [style.border-color]="hasConfluenceSyncIssue(document) ? 'rgba(239, 68, 68, 0.5)' : 'var(--app-border)'"
                [style.background-color]="hasConfluenceSyncIssue(document) ? 'rgba(239, 68, 68, 0.06)' : 'var(--app-surface)'"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium text-[var(--app-text)]">{{ document.original_name }}</p>
                  @if (document.metadata?.chunks_count) {
                    <p class="mt-0.5 text-xs text-[var(--app-text-muted)]">{{ document.metadata?.chunks_count }} chunks - {{ document.metadata?.pages_count ?? 0 }} pages</p>
                  }
                  @if (isConfluenceDocument(document) && document.source_url) {
                    <p class="mt-0.5 truncate text-xs text-[var(--app-text-muted)]">{{ document.source_url }}</p>
                  }
                  @if (isConfluenceDocument(document) && isConfluenceDocumentOutdated(document)) {
                    <p class="mt-0.5 text-xs font-medium text-[var(--app-danger)]">Outdated in RAG: Confluence page changed and needs re-sync.</p>
                  }
                  @if (isConfluenceDocument(document) && confluenceDocumentError(document)) {
                    <p class="mt-0.5 text-xs font-medium text-[var(--app-danger)]">Sync error: {{ confluenceDocumentError(document) }}</p>
                  }
                </div>

                <div class="flex shrink-0 items-center gap-2">
                  @if (isConfluenceDocument(document) && isConfluenceDocumentUnsynced(document)) {
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-amber-400/10 px-2.5 py-1 text-xs font-medium text-amber-500">
                      <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                      Not synced
                    </span>
                  }
                  @if (isConfluenceDocument(document) && isConfluenceDocumentOutdated(document)) {
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--app-danger)]/10 px-2.5 py-1 text-xs font-medium text-[var(--app-danger)]">
                      <span class="h-1.5 w-1.5 rounded-full bg-[var(--app-danger)]"></span>
                      Outdated
                    </span>
                  }
                  @if (isConfluenceDocument(document) && confluenceDocumentError(document)) {
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--app-danger)]/10 px-2.5 py-1 text-xs font-medium text-[var(--app-danger)]">
                      <span class="h-1.5 w-1.5 rounded-full bg-[var(--app-danger)]"></span>
                      Sync error
                    </span>
                  }

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

                  @if (isConfluenceDocument(document)) {
                  <button
                    type="button"
                    (click)="resyncConfluenceDocument(document)"
                    [disabled]="isResyncingDocument(document.id)"
                    class="rounded-md border border-[var(--app-border)] px-2.5 py-1 text-xs font-medium text-[var(--app-text-muted)] transition hover:border-[var(--app-accent)]/40 hover:bg-[var(--app-accent-soft)] hover:text-[var(--app-accent)] disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {{ isResyncingDocument(document.id) ? 'Queueing...' : 'Resync' }}
                  </button>
                  }

                  <button
                    type="button"
                    (click)="openPreviewModal(document)"
                    [disabled]="isLoadingDocumentPreview(document.id)"
                    class="rounded-md border border-[var(--app-border)] px-2.5 py-1 text-xs font-medium text-[var(--app-text-muted)] transition hover:border-[var(--app-accent)]/40 hover:bg-[var(--app-accent-soft)] hover:text-[var(--app-accent)] disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    {{ isLoadingDocumentPreview(document.id) ? 'Loading...' : 'View text' }}
                  </button>

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

            @if (documentsTotalPages() > 1) {
              <div class="mt-2 flex items-center justify-between">
                <p class="text-xs text-[var(--app-text-muted)]">Page {{ documentsPage() }} of {{ documentsTotalPages() }}</p>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    (click)="goToDocumentsPage(documentsPage() - 1)"
                    [disabled]="documentsPage() <= 1"
                    class="rounded-md border border-[var(--app-border)] px-2 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] disabled:opacity-50"
                  >
                    Prev
                  </button>
                  <button
                    type="button"
                    (click)="goToDocumentsPage(documentsPage() + 1)"
                    [disabled]="documentsPage() >= documentsTotalPages()"
                    class="rounded-md border border-[var(--app-border)] px-2 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface)] hover:text-[var(--app-text)] disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
              </div>
            }
          </div>
        }
      </div>

      @if (previewDocument()) {
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 px-4" (click)="closePreviewModal()">
          <div class="flex max-h-[85vh] w-full max-w-4xl flex-col rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] shadow-xl" (click)="$event.stopPropagation()">
            <div class="border-b border-[var(--app-border)] px-5 py-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <h4 class="truncate text-base font-semibold text-[var(--app-text)]">{{ previewDocument()!.original_name }}</h4>
                  <p class="mt-1 text-xs text-[var(--app-text-muted)]">Extracted chunks: {{ previewChunksTotal() }}</p>
                </div>
                <button
                  type="button"
                  (click)="closePreviewModal()"
                  class="rounded-md border border-[var(--app-border)] px-2.5 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)]"
                >
                  Close
                </button>
              </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto px-5 py-4">
              @if (isLoadingPreviewChunks()) {
                <div class="space-y-2">
                  <div class="h-16 animate-pulse rounded-lg bg-[var(--app-surface-2)]"></div>
                  <div class="h-16 animate-pulse rounded-lg bg-[var(--app-surface-2)]"></div>
                </div>
              } @else if (previewError()) {
                <p class="rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">{{ previewError() }}</p>
              } @else if (previewChunks().length === 0) {
                <p class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] px-3 py-2 text-sm text-[var(--app-text-muted)]">No extracted text chunks available for this document yet.</p>
              } @else {
                <div class="space-y-3">
                  @for (chunk of previewChunks(); track chunk.id) {
                    <article class="rounded-lg border border-[var(--app-border)] bg-[var(--app-surface-2)] p-3">
                      <p class="text-xs font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">
                        Chunk #{{ chunk.chunk_index }}
                        @if (chunk.page_from || chunk.page_to) {
                          · Pages {{ chunk.page_from ?? '?' }}-{{ chunk.page_to ?? '?' }}
                        }
                      </p>
                      <pre class="mt-2 whitespace-pre-wrap break-words text-sm text-[var(--app-text)]">{{ chunk.content }}</pre>
                    </article>
                  }
                </div>
              }
            </div>

            @if (previewChunksTotalPages() > 1) {
              <div class="flex items-center justify-between border-t border-[var(--app-border)] px-5 py-3">
                <p class="text-xs text-[var(--app-text-muted)]">Page {{ previewChunksPage() }} of {{ previewChunksTotalPages() }}</p>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    (click)="goToPreviewChunksPage(previewChunksPage() - 1)"
                    [disabled]="previewChunksPage() <= 1 || isLoadingPreviewChunks()"
                    class="rounded-md border border-[var(--app-border)] px-2 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)] disabled:opacity-50"
                  >
                    Prev
                  </button>
                  <button
                    type="button"
                    (click)="goToPreviewChunksPage(previewChunksPage() + 1)"
                    [disabled]="previewChunksPage() >= previewChunksTotalPages() || isLoadingPreviewChunks()"
                    class="rounded-md border border-[var(--app-border)] px-2 py-1 text-xs text-[var(--app-text-muted)] transition hover:bg-[var(--app-surface-2)] hover:text-[var(--app-text)] disabled:opacity-50"
                  >
                    Next
                  </button>
                </div>
              </div>
            }
          </div>
        </div>
      }

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
  protected readonly resyncingDocumentIds = signal<Set<number>>(new Set());
  protected readonly previewDocument = signal<ProjectDocument | null>(null);
  protected readonly previewChunks = signal<DocumentChunkPreview[]>([]);
  protected readonly isLoadingPreviewChunks = signal(false);
  protected readonly previewError = signal('');
  protected readonly previewChunksPage = signal(1);
  protected readonly previewChunksPerPage = signal(25);
  protected readonly previewChunksTotal = signal(0);
  protected readonly previewChunksLastPage = signal(1);
  protected readonly pendingDeleteDocument = signal<ProjectDocument | null>(null);
  protected readonly crawledUrls = signal<CrawledUrl[]>([]);
  protected readonly isCrawling = signal(false);
  protected readonly crawlError = signal('');
  protected crawlUrl = '';
   protected readonly activeIngestionTab = signal<'upload' | 'crawler' | 'confluence'>('upload');
   protected readonly documentsPage = signal(1);
   protected readonly documentsPerPage = signal(10);
   protected readonly documentsTotal = signal(0);
   protected readonly documentsLastPage = signal(1);
   protected readonly crawledUrlsPage = signal(1);
   protected readonly crawledUrlsPerPage = signal(10);
   protected readonly crawledUrlsTotal = signal(0);
   protected readonly crawledUrlsLastPage = signal(1);
   protected readonly confluenceSpacesPage = signal(1);
   protected readonly confluenceSpacesPerPage = signal(10);
   protected readonly confluenceSpacesTotal = signal(0);
   protected readonly confluenceSpacesLastPage = signal(1);
   protected readonly projectTenantId = signal<number | null>(null);
   protected readonly showConfluenceForm = signal(false);
  protected readonly confluenceConnections = signal<AtlassianConnection[]>([]);
  protected readonly confluenceConnectionId = signal<number | null>(null);
  protected readonly availableConfluenceSpaces = signal<ConfluenceSpace[]>([]);
  protected readonly selectedProjectSpaces = signal<ProjectConfluenceSpace[]>([]);
  protected readonly selectedSpaceKeys = signal<Set<string>>(new Set());
  protected readonly isConfluenceConnecting = signal(false);
  protected readonly isConfluenceLoadingSpaces = signal(false);
  protected readonly isConfluenceSavingSpaces = signal(false);
  protected readonly isConfluenceSyncing = signal(false);
  protected readonly isConfluenceSelectingAll = signal(false);
  protected readonly removingProjectSpaceIds = signal<Set<number>>(new Set());
  protected readonly confluenceError = signal('');
  protected readonly confluenceSuccess = signal('');
  protected confluenceBaseUrl = '';
  protected confluenceEmail = '';
  protected confluenceApiToken = '';
  protected confluenceCloudId = '';
  protected readonly pageSizeOptions = [10, 25, 50, 100];
  protected readonly confluenceSpaceLookup = signal<Map<string, ConfluenceSpace>>(new Map());

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

    this.api.listDocuments(this.requireProjectId(), {
      page: this.documentsPage(),
      per_page: this.documentsPerPage(),
    }).subscribe({
      next: ({ data, meta }) => {
        this.documents.set(data);
        this.applyPaginationMeta(meta, this.documentsPage, this.documentsPerPage, this.documentsTotal, this.documentsLastPage);
      },
      error: () => {
        this.uploadError.set('Failed to load documents.');
        this.isLoadingDocs.set(false);
      },
      complete: () => this.isLoadingDocs.set(false),
    });
  }

  protected loadCrawledUrls(): void {
    this.api.listCrawledUrls(this.requireProjectId(), {
      page: this.crawledUrlsPage(),
      per_page: this.crawledUrlsPerPage(),
    }).subscribe({
      next: ({ data, meta }) => {
        this.crawledUrls.set(data);
        this.applyPaginationMeta(meta, this.crawledUrlsPage, this.crawledUrlsPerPage, this.crawledUrlsTotal, this.crawledUrlsLastPage);
      },
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
        this.upsertConfluenceConnection(data);
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

     this.api.listConfluenceSpaces(tenantId, connectionId, {
       page: this.confluenceSpacesPage(),
       per_page: this.confluenceSpacesPerPage(),
       all: true,
     }).subscribe({
       next: ({ data, meta }) => {
         this.availableConfluenceSpaces.set(data);
         this.applyPaginationMeta(meta, this.confluenceSpacesPage, this.confluenceSpacesPerPage, this.confluenceSpacesTotal, this.confluenceSpacesLastPage);
         this.mergeConfluenceSpacesIntoLookup(data);
       },
       error: (error: HttpErrorResponse) => {
         this.confluenceError.set(error.error?.message ?? 'Failed to load Confluence spaces.');
       },
       complete: () => this.isConfluenceLoadingSpaces.set(false),
     });
   }

   protected onConfluenceConnectionSelected(value: string | number | null): void {
     const normalized = Number(value);

     if (!Number.isFinite(normalized) || normalized <= 0) {
       this.confluenceConnectionId.set(null);
       this.availableConfluenceSpaces.set([]);
       this.confluenceSpaceLookup.set(new Map());
       this.confluenceSpacesPage.set(1);

       return;
     }

     this.confluenceConnectionId.set(normalized);
     this.confluenceSuccess.set('');
     this.confluenceError.set('');
     this.availableConfluenceSpaces.set([]);
     this.confluenceSpaceLookup.set(new Map());
     this.confluenceSpacesPage.set(1);
   }

  protected areAllVisibleConfluenceSpacesSelected(): boolean {
    const visibleSpaces = this.availableConfluenceSpaces();

    if (visibleSpaces.length === 0) {
      return false;
    }

    const selectedKeys = this.selectedSpaceKeys();

    return visibleSpaces.every((space) => selectedKeys.has(space.key));
  }

   protected toggleSelectAllMatchingConfluenceSpaces(event: Event): void {
     const tenantId = this.projectTenantId();
     const connectionId = this.confluenceConnectionId();

     if (!tenantId || !connectionId) {
       return;
     }

     const checked = (event.target as HTMLInputElement).checked;
     this.isConfluenceSelectingAll.set(true);
     this.confluenceError.set('');

     this.api.listConfluenceSpaces(tenantId, connectionId, {
       page: 1,
       per_page: 100,
       all: true,
     }).subscribe({
       next: ({ data }) => {
         const keys = data.map((space) => space.key);
         this.mergeConfluenceSpacesIntoLookup(data);

         this.selectedSpaceKeys.update((current) => {
           const next = new Set(current);

           for (const key of keys) {
             if (checked) {
               next.add(key);
             } else {
               next.delete(key);
             }
           }

           return next;
         });
       },
       error: (error: HttpErrorResponse) => {
         this.confluenceError.set(error.error?.message ?? 'Failed to select all spaces.');
       },
       complete: () => this.isConfluenceSelectingAll.set(false),
     });
   }

  protected setDocumentsPerPage(value: number): void {
    this.documentsPerPage.set(Number(value));
    this.documentsPage.set(1);
    this.loadDocuments();
  }

  protected goToDocumentsPage(page: number): void {
    if (page < 1 || page > this.documentsTotalPages()) {
      return;
    }

    this.documentsPage.set(page);
    this.loadDocuments();
  }

  protected setCrawledUrlsPerPage(value: number): void {
    this.crawledUrlsPerPage.set(Number(value));
    this.crawledUrlsPage.set(1);
    this.loadCrawledUrls();
  }

  protected goToCrawledUrlsPage(page: number): void {
    if (page < 1 || page > this.crawledUrlsTotalPages()) {
      return;
    }

    this.crawledUrlsPage.set(page);
    this.loadCrawledUrls();
  }

  protected setConfluenceSpacesPerPage(value: number): void {
    this.confluenceSpacesPerPage.set(Number(value));
    this.confluenceSpacesPage.set(1);
    this.loadConfluenceSpaces();
  }

  protected isSpaceSelected(spaceKey: string): boolean {
    return this.selectedSpaceKeys().has(spaceKey);
  }

  protected isRemovingProjectSpace(spaceId: number): boolean {
    return this.removingProjectSpaceIds().has(spaceId);
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
    const spaces = Array.from(selectedKeys)
      .map((key) => this.confluenceSpaceLookup().get(key) ?? ({ key, name: key, type: 'global' } satisfies ConfluenceSpace));

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

  protected removeProjectConfluenceSpace(space: ProjectConfluenceSpace): void {
    if (this.isRemovingProjectSpace(space.id)) {
      return;
    }

    this.removingProjectSpaceIds.update((ids) => {
      const next = new Set(ids);
      next.add(space.id);

      return next;
    });

    this.confluenceError.set('');
    this.confluenceSuccess.set('');

    this.api.removeProjectConfluenceSpace(this.requireProjectId(), space.id).subscribe({
      next: () => {
        this.selectedProjectSpaces.update((spaces) => spaces.filter((item) => item.id !== space.id));
        this.selectedSpaceKeys.update((keys) => {
          const next = new Set(keys);
          next.delete(space.space_key);

          return next;
        });
        this.confluenceSuccess.set(`Removed ${space.space_name} from this project.`);
      },
      error: (error: HttpErrorResponse) => {
        this.confluenceError.set(error.error?.message ?? 'Failed to remove Confluence space from this project.');
      },
      complete: () => {
        this.removingProjectSpaceIds.update((ids) => {
          const next = new Set(ids);
          next.delete(space.id);

          return next;
        });
      },
    });
  }

  private loadConfluenceContext(): void {
    const projectId = this.requireProjectId();

    this.api.listProjects().subscribe({
      next: ({ data }) => {
        const project = data.find((item) => item.id === projectId);
        const tenantId = project?.tenant_id ?? null;
        this.projectTenantId.set(tenantId);

        if (tenantId) {
          this.loadConfluenceConnections(tenantId);
        }
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

        this.mergeConfluenceSpacesIntoLookup(data.map((item) => ({
          id: item.space_id,
          key: item.space_key,
          name: item.space_name,
          type: item.space_type,
        })));

        this.selectedSpaceKeys.set(new Set(data.map((item) => item.space_key)));
      },
    });
  }

  private loadConfluenceConnections(tenantId: number): void {
    this.api.listConfluenceConnections(tenantId).subscribe({
      next: ({ data }) => {
        this.confluenceConnections.set(data);

        if (!this.confluenceConnectionId() && data.length > 0) {
          this.confluenceConnectionId.set(data[0].id);
        }
      },
      error: () => {
        this.confluenceError.set('Failed to load saved Confluence connections.');
      },
    });
  }

  private upsertConfluenceConnection(connection: AtlassianConnection): void {
    this.confluenceConnections.update((connections) => [connection, ...connections.filter((item) => item.id !== connection.id)]);
  }

  protected documentsTotalPages(): number {
    return this.documentsLastPage();
  }

  protected crawledUrlsTotalPages(): number {
    return this.crawledUrlsLastPage();
  }


  private applyPaginationMeta(
    meta: PaginationMeta,
    pageSignal: { set(value: number): void },
    perPageSignal: { set(value: number): void },
    totalSignal: { set(value: number): void },
    lastPageSignal: { set(value: number): void },
  ): void {
    pageSignal.set(meta.current_page);
    perPageSignal.set(meta.per_page);
    totalSignal.set(meta.total);
    lastPageSignal.set(meta.last_page);
  }

  private mergeConfluenceSpacesIntoLookup(spaces: Array<Pick<ConfluenceSpace, 'key' | 'name'> & Partial<ConfluenceSpace>>): void {
    this.confluenceSpaceLookup.update((current) => {
      const next = new Map(current);

      for (const space of spaces) {
        next.set(space.key, {
          id: space.id ?? null,
          key: space.key,
          name: space.name,
          type: space.type ?? 'global',
        });
      }

      return next;
    });
  }

  protected isDeletingDocument(documentId: number): boolean {
    return this.deletingDocumentIds().has(documentId);
  }

  protected isResyncingDocument(documentId: number): boolean {
    return this.resyncingDocumentIds().has(documentId);
  }

  protected isConfluenceDocument(document: ProjectDocument): boolean {
    return document.ingestion_type === 'confluence';
  }

  protected isConfluenceDocumentUnsynced(document: ProjectDocument): boolean {
    if (!this.isConfluenceDocument(document)) {
      return false;
    }

    return document.status === 'pending' || document.status === 'processing';
  }

  protected hasConfluenceSyncIssue(document: ProjectDocument): boolean {
    if (!this.isConfluenceDocument(document)) {
      return false;
    }

    return this.isConfluenceDocumentOutdated(document) || Boolean(this.confluenceDocumentError(document));
  }

  protected isConfluenceDocumentOutdated(document: ProjectDocument): boolean {
    if (!this.isConfluenceDocument(document)) {
      return false;
    }

    const synced = document.metadata?.synced_external_updated_at ?? document.metadata?.external_updated_at;
    const latest = document.metadata?.latest_external_updated_at;

    return Boolean(synced && latest && synced !== latest);
  }

  protected confluenceDocumentError(document: ProjectDocument): string {
    if (!this.isConfluenceDocument(document)) {
      return '';
    }

    if (document.status === 'failed') {
      return document.metadata?.last_error ?? 'Confluence page sync failed.';
    }

    if (document.metadata?.last_status === 'failed') {
      return document.metadata?.last_error ?? 'Last Confluence sync failed.';
    }

    return '';
  }

  protected resyncConfluenceDocument(document: ProjectDocument): void {
    if (!this.isConfluenceDocument(document) || this.isResyncingDocument(document.id)) {
      return;
    }

    this.resyncingDocumentIds.update((ids) => {
      const next = new Set(ids);
      next.add(document.id);

      return next;
    });

    this.confluenceError.set('');
    this.confluenceSuccess.set('');

    this.api.resyncConfluenceDocument(this.requireProjectId(), document.id).subscribe({
      next: ({ message }) => {
        this.confluenceSuccess.set(message || 'Confluence page re-sync queued.');
        this.loadDocuments();
      },
      error: (error: HttpErrorResponse) => {
        this.confluenceError.set(error.error?.message ?? 'Failed to queue Confluence page re-sync.');
      },
      complete: () => {
        this.resyncingDocumentIds.update((ids) => {
          const next = new Set(ids);
          next.delete(document.id);

          return next;
        });
      },
    });
  }

  protected isLoadingDocumentPreview(documentId: number): boolean {
    return this.isLoadingPreviewChunks() && this.previewDocument()?.id === documentId;
  }

  protected openPreviewModal(document: ProjectDocument): void {
    this.previewDocument.set(document);
    this.previewChunks.set([]);
    this.previewError.set('');
    this.previewChunksPage.set(1);
    this.loadDocumentPreviewChunks();
  }

  protected closePreviewModal(): void {
    this.previewDocument.set(null);
    this.previewChunks.set([]);
    this.previewError.set('');
    this.isLoadingPreviewChunks.set(false);
  }

  protected previewChunksTotalPages(): number {
    return this.previewChunksLastPage();
  }

  protected goToPreviewChunksPage(page: number): void {
    if (page < 1 || page > this.previewChunksTotalPages() || this.previewDocument() === null) {
      return;
    }

    this.previewChunksPage.set(page);
    this.loadDocumentPreviewChunks();
  }

  private loadDocumentPreviewChunks(): void {
    const document = this.previewDocument();

    if (!document) {
      return;
    }

    this.isLoadingPreviewChunks.set(true);
    this.previewError.set('');

    this.api.listDocumentContent(this.requireProjectId(), document.id, {
      page: this.previewChunksPage(),
      per_page: this.previewChunksPerPage(),
    }).subscribe({
      next: ({ data, meta }) => {
        this.previewChunks.set(data);
        this.applyPaginationMeta(meta, this.previewChunksPage, this.previewChunksPerPage, this.previewChunksTotal, this.previewChunksLastPage);
      },
      error: (error: HttpErrorResponse) => {
        this.previewError.set(error.error?.message ?? 'Failed to load document text preview.');
      },
      complete: () => this.isLoadingPreviewChunks.set(false),
    });
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


import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { ProjectDocument, RagApiService } from '../core/rag-api.service';

@Component({
  selector: 'app-project-documents-page',
  standalone: true,
  template: `
    <section class="space-y-6">
      <div class="rounded-xl border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
        <h3 class="text-sm font-semibold text-[var(--app-text)]">Upload document</h3>
        <p class="mt-1 text-xs text-[var(--app-text-muted)]">Files are queued for parsing, chunking, and embedding automatically.</p>

        @if (uploadError()) {
          <div class="mt-3 flex items-start gap-2 rounded-lg border border-[var(--app-danger)]/40 bg-[var(--app-danger)]/10 px-3 py-2 text-sm text-[var(--app-danger)]">
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <span>{{ uploadError() }}</span>
          </div>
        }

        <div
          class="relative mt-4 rounded-lg border-2 border-[var(--app-border)] border-dashed p-8 text-center transition"
          [class.border-cyan-400]="isDragging()"
          [class.bg-cyan-400/5]="isDragging()"
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

  ngOnInit(): void {
    this.loadDocuments();
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

    this.api.listDocuments(this.requireProjectId()).subscribe({
      next: ({ data }) => this.documents.set(data),
      error: () => {
        this.uploadError.set('Failed to load documents.');
        this.isLoadingDocs.set(false);
      },
      complete: () => this.isLoadingDocs.set(false),
    });
  }

  private requireProjectId(): number {
    return Number(this.route.parent?.snapshot.paramMap.get('projectId'));
  }
}


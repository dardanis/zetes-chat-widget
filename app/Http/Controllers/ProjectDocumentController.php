<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlProjectWebsiteJob;
use App\Jobs\ProcessProjectDocumentJob;
use App\Jobs\ResyncConfluenceDocumentJob;
use App\Models\DocumentChunk;
use App\Models\ProjectDocument;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ValueError;

class ProjectDocumentController extends Controller
{
    private const SUPPORTED_DOCUMENT_EXTENSIONS = ['pdf', 'txt', 'md', 'csv', 'xlsx', 'xls', 'docx', 'html', 'json', 'xml'];

    public function __construct(private readonly ProjectAccessService $accessService) {}

    public function index(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($payload['per_page'] ?? 10);
        $page = (int) ($payload['page'] ?? 1);

        $documents = ProjectDocument::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $documents->items(),
            'meta' => $this->paginationMeta($documents),
        ]);
    }

    public function store(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'file' => ['required', 'file', 'extensions:'.implode(',', self::SUPPORTED_DOCUMENT_EXTENSIONS), 'max:20480'],
        ]);

        $file = $payload['file'];
        $originalName = $file->getClientOriginalName();
        $clientMimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream';
        $fileSize = (int) ($file->getSize() ?? $file->getClientSize() ?? 0);

        if (! $file->isValid()) {
            return response()->json([
                'message' => 'Uploaded file is invalid. Please select the PDF again and retry.',
                'debug' => [
                    'upload_error_code' => $file->getError(),
                    'upload_error_message' => $file->getErrorMessage(),
                    'client_name' => $originalName,
                    'client_size' => $fileSize,
                    'client_mime' => $clientMimeType,
                ],
            ], 422);
        }

        try {
            $disk = Storage::disk('local');
            $directory = 'rag/documents';
            $disk->makeDirectory($directory);

            $extension = strtolower($file->getClientOriginalExtension() ?: '');

            if (! in_array($extension, self::SUPPORTED_DOCUMENT_EXTENSIONS, true)) {
                return response()->json([
                    'message' => 'Unsupported document type. Supported types are: '.implode(', ', self::SUPPORTED_DOCUMENT_EXTENSIONS).'.',
                ], 422);
            }

            $filename = Str::uuid()->toString().'.'.$extension;
            $targetPath = $disk->path($directory);

            $file->move($targetPath, $filename);

            $path = $directory.'/'.$filename;
        } catch (ValueError) {
            return response()->json([
                'message' => 'Uploaded file path is empty. Please select the PDF again and retry.',
                'debug' => [
                    'upload_error_code' => $file->getError(),
                    'upload_error_message' => $file->getErrorMessage(),
                    'client_name' => $originalName,
                    'client_size' => $fileSize,
                    'client_mime' => $clientMimeType,
                ],
            ], 422);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to store uploaded file. Please retry.',
                'debug' => [
                    'exception' => get_class($exception),
                    'error' => $exception->getMessage(),
                ],
            ], 422);
        }

        if (! is_string($path) || $path === '') {
            return response()->json([
                'message' => 'Unable to store uploaded file. Please retry.',
            ], 422);
        }

        $document = ProjectDocument::query()->create([
            'tenant_id' => $resolvedProject->tenant_id,
            'project_id' => $resolvedProject->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $originalName,
            'storage_path' => $path,
            'mime_type' => $clientMimeType,
            'file_size' => $fileSize,
            'status' => 'pending',
            'ingestion_type' => $extension,
            'metadata' => [
                'file_type' => $extension,
                'original_name' => $originalName,
                'last_status' => 'queued',
            ],
        ]);

        ProcessProjectDocumentJob::dispatch($document->id);

        return response()->json(['data' => $document], 202);
    }

    public function content(Request $request, int $project, int $document): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $projectDocument = ProjectDocument::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->whereKey($document)
            ->firstOrFail();

        $perPage = (int) ($payload['per_page'] ?? 25);
        $page = (int) ($payload['page'] ?? 1);

        $chunks = DocumentChunk::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('project_document_id', $projectDocument->id)
            ->orderBy('chunk_index')
            ->paginate($perPage, ['id', 'chunk_index', 'page_from', 'page_to', 'content', 'metadata'], 'page', $page);

        return response()->json([
            'data' => $chunks->items(),
            'meta' => $this->paginationMeta($chunks),
        ]);
    }

    public function crawl(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'url' => ['required', 'url:'.implode(',', ['http', 'https'])],
            'max_pages' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $maxPages = (int) ($payload['max_pages'] ?? config('rag.crawler.max_pages', 40));

        CrawlProjectWebsiteJob::dispatch(
            projectId: $resolvedProject->id,
            userId: $request->user()->id,
            startUrl: (string) $payload['url'],
            maxPages: $maxPages,
        );

        return response()->json([
            'message' => 'Website crawl queued.',
            'data' => [
                'url' => (string) $payload['url'],
                'max_pages' => $maxPages,
            ],
        ], 202);
    }

    public function crawledUrls(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($payload['per_page'] ?? 10);
        $page = (int) ($payload['page'] ?? 1);

        $crawledUrls = ProjectDocument::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('ingestion_type', 'web')
            ->whereNotNull('source_url')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn (ProjectDocument $document): array => [
                'id' => $document->id,
                'url' => $document->source_url,
                'title' => data_get($document->metadata, 'title', $document->original_name),
                'status' => $document->status,
                'chunks_count' => (int) data_get($document->metadata, 'chunks_count', 0),
                'processed_at' => $document->processed_at?->toIso8601String(),
                'updated_at' => $document->updated_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $crawledUrls->items(),
            'meta' => $this->paginationMeta($crawledUrls),
        ]);
    }

    public function destroy(Request $request, int $project, int $document): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $projectDocument = ProjectDocument::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->whereKey($document)
            ->firstOrFail();

        if (is_string($projectDocument->storage_path) && $projectDocument->storage_path !== '') {
            Storage::disk('local')->delete($projectDocument->storage_path);
        }

        $projectDocument->delete();

        return response()->json([], 204);
    }

    public function resyncConfluence(Request $request, int $project, int $document): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $projectDocument = ProjectDocument::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->whereKey($document)
            ->firstOrFail();

        if ($projectDocument->ingestion_type !== 'confluence') {
            return response()->json([
                'message' => 'Only Confluence documents can be re-synced individually.',
            ], 422);
        }

        $projectDocument->update([
            'status' => 'pending',
            'processed_at' => null,
            'metadata' => array_filter(array_merge(
                is_array($projectDocument->metadata) ? $projectDocument->metadata : [],
                [
                    'last_status' => 'queued',
                    'last_error' => null,
                    'queued_at' => now()->toIso8601String(),
                ]
            ), static fn (mixed $value): bool => $value !== null),
        ]);

        ResyncConfluenceDocumentJob::dispatch($projectDocument->id);

        return response()->json([
            'message' => 'Confluence page re-sync queued.',
            'data' => [
                'document_id' => $projectDocument->id,
            ],
        ], 202);
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}

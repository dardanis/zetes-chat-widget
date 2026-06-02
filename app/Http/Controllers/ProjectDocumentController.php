<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlProjectWebsiteJob;
use App\Jobs\ProcessProjectDocumentJob;
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
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        $file = $payload['file'];
        $originalName = $file->getClientOriginalName();
        $clientMimeType = $file->getClientMimeType() ?: 'application/pdf';
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

            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
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
            'ingestion_type' => 'pdf',
        ]);

        ProcessProjectDocumentJob::dispatch($document->id);

        return response()->json(['data' => $document], 202);
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


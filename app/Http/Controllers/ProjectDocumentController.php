<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProjectDocumentJob;
use App\Models\ProjectDocument;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $documents = ProjectDocument::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->latest('id')
            ->get();

        return response()->json(['data' => $documents]);
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
        ]);

        ProcessProjectDocumentJob::dispatch($document->id);

        return response()->json(['data' => $document], 202);
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
}


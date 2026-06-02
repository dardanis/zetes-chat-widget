<?php

namespace App\Http\Controllers;

use App\Jobs\SyncProjectConfluenceSpaceJob;
use App\Models\AtlassianConnection;
use App\Models\ProjectConfluenceSpace;
use App\Services\Rag\ConfluenceApiService;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfluenceIntegrationController extends Controller
{
    public function __construct(private readonly ProjectAccessService $accessService) {}

    public function indexConnections(Request $request, int $tenant): JsonResponse
    {
        $this->ensureTenantMembership($request, $tenant);

        $connections = AtlassianConnection::query()
            ->where('tenant_id', $tenant)
            ->orderByDesc('is_active')
            ->latest('id')
            ->get();

        return response()->json(['data' => $connections]);
    }

    public function storeConnection(Request $request, int $tenant): JsonResponse
    {
        $this->ensureTenantMembership($request, $tenant);

        $payload = $request->validate([
            'base_url' => ['required', 'url:http,https', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'api_token' => ['required', 'string', 'max:255'],
            'cloud_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $connection = AtlassianConnection::query()->create([
            'tenant_id' => $tenant,
            'created_by' => $request->user()->id,
            'base_url' => rtrim((string) $payload['base_url'], '/'),
            'email' => (string) $payload['email'],
            'api_token' => (string) $payload['api_token'],
            'cloud_id' => $payload['cloud_id'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['data' => $connection], 201);
    }

    public function listSpaces(Request $request, int $tenant, AtlassianConnection $connection, ConfluenceApiService $confluence): JsonResponse
    {
        $this->ensureTenantMembership($request, $tenant);
        abort_if($connection->tenant_id !== $tenant, 404);

        $spaces = $confluence->listSpaces($connection);

        return response()->json(['data' => $spaces]);
    }

    public function indexProjectSpaces(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $spaces = ProjectConfluenceSpace::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('is_enabled', true)
            ->latest('id')
            ->get();

        return response()->json(['data' => $spaces]);
    }

    public function updateProjectSpaces(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'connection_id' => ['required', 'integer', 'exists:atlassian_connections,id'],
            'spaces' => ['required', 'array'],
            'spaces.*.id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'spaces.*.key' => ['required', 'string', 'max:255'],
            'spaces.*.name' => ['required', 'string', 'max:255'],
            'spaces.*.type' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $connection = AtlassianConnection::query()
            ->whereKey($payload['connection_id'])
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->firstOrFail();

        $selectedKeys = collect((array) $payload['spaces'])
            ->map(static fn (array $space): string => (string) $space['key'])
            ->filter(static fn (string $key): bool => $key !== '')
            ->values();

        DB::transaction(function () use ($payload, $resolvedProject, $connection, $request, $selectedKeys): void {
            ProjectConfluenceSpace::query()
                ->where('tenant_id', $resolvedProject->tenant_id)
                ->where('project_id', $resolvedProject->id)
                ->where('atlassian_connection_id', $connection->id)
                ->whereNotIn('space_key', $selectedKeys->all())
                ->update(['is_enabled' => false]);

            foreach ((array) $payload['spaces'] as $space) {
                ProjectConfluenceSpace::query()->updateOrCreate(
                    [
                        'tenant_id' => $resolvedProject->tenant_id,
                        'project_id' => $resolvedProject->id,
                        'atlassian_connection_id' => $connection->id,
                        'space_key' => (string) $space['key'],
                    ],
                    [
                        'selected_by' => $request->user()->id,
                        'space_id' => $space['id'] ?? null,
                        'space_name' => (string) $space['name'],
                        'space_type' => (string) ($space['type'] ?? 'global'),
                        'is_enabled' => true,
                    ],
                );
            }
        });

        $spaces = ProjectConfluenceSpace::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('atlassian_connection_id', $connection->id)
            ->where('is_enabled', true)
            ->latest('id')
            ->get();

        return response()->json(['data' => $spaces]);
    }

    public function syncProjectSpaces(Request $request, int $project): JsonResponse
    {
        $resolvedProject = $this->accessService->resolveProjectForUser($request->user(), $project);

        $payload = $request->validate([
            'connection_id' => ['sometimes', 'nullable', 'integer', 'exists:atlassian_connections,id'],
        ]);

        $spacesQuery = ProjectConfluenceSpace::query()
            ->where('tenant_id', $resolvedProject->tenant_id)
            ->where('project_id', $resolvedProject->id)
            ->where('is_enabled', true);

        if (isset($payload['connection_id'])) {
            $spacesQuery->where('atlassian_connection_id', (int) $payload['connection_id']);
        }

        $spaceIds = $spacesQuery->pluck('id');

        foreach ($spaceIds as $spaceId) {
            SyncProjectConfluenceSpaceJob::dispatch((int) $spaceId);
        }

        return response()->json([
            'message' => 'Confluence sync queued.',
            'data' => [
                'spaces_queued' => $spaceIds->count(),
            ],
        ], 202);
    }

    private function ensureTenantMembership(Request $request, int $tenantId): void
    {
        $isMember = $request->user()->tenants()->whereKey($tenantId)->exists();

        abort_unless($isMember, 403);
    }
}


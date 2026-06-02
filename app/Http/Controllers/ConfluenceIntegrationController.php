<?php

namespace App\Http\Controllers;

use App\Jobs\SyncProjectConfluenceSpaceJob;
use App\Models\AtlassianConnection;
use App\Models\ProjectConfluenceSpace;
use App\Services\Rag\ConfluenceApiService;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $payload = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $page = (int) ($payload['page'] ?? 1);
        $perPage = (int) ($payload['per_page'] ?? 10);
        $query = strtolower(trim((string) ($payload['q'] ?? '')));
        $loadAll = (bool) ($payload['all'] ?? false);

        $spaces = collect($confluence->listSpaces($connection));

        if ($query !== '') {
            $spaces = $spaces->filter(static function (array $space) use ($query): bool {
                $name = strtolower((string) ($space['name'] ?? ''));
                $key = strtolower((string) ($space['key'] ?? ''));

                return str_contains($name, $query) || str_contains($key, $query);
            })->values();
        }

        if ($loadAll) {
            return response()->json([
                'data' => $spaces->values()->all(),
                'meta' => [
                    'current_page' => 1,
                    'per_page' => max(1, $spaces->count()),
                    'total' => $spaces->count(),
                    'last_page' => 1,
                ],
            ]);
        }

        $total = $spaces->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $safePage = min(max($page, 1), $lastPage);
        $items = $spaces
            ->slice(($safePage - 1) * $perPage, $perPage)
            ->values()
            ->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $safePage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ]);
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


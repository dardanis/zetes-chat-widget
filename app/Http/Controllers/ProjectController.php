<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tenant;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), 'projects.view'), 403);

        $projects = $this->access->scopeProjectsFor(
            $request->user(),
            Project::query()->with(['country', 'tenant'])
        )->latest('id')->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), 'projects.create'), 403);
        $this->normalizeCountryCode($request);

        $payload = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')->where('status', 'active')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $tenant = Tenant::query()->findOrFail($payload['tenant_id']);

        abort_unless($tenant->country_code === $payload['country_code'], 422, 'Project country must match the tenant country.');
        abort_unless($this->access->canAccessCountry($request->user(), $payload['country_code']), 403);

        $widgetSecret = Str::random(48);

        $project = Project::query()->create([
            'tenant_id' => $payload['tenant_id'],
            'country_code' => $payload['country_code'],
            'owner_id' => $request->user()->id,
            'name' => $payload['name'],
            'slug' => Str::slug($payload['name']).'-'.Str::lower(Str::random(6)),
            'widget_key' => Str::random(40),
            'widget_secret' => $widgetSecret,
            'widget_secret_hash' => Hash::make($widgetSecret),
            'status' => $payload['status'] ?? 'active',
        ]);
        $project->users()->attach($request->user()->id, ['role' => 'owner']);

        return response()->json([
            'data' => $project->load(['country', 'tenant']),
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.update'), 403);
        $this->normalizeCountryCode($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')->where('status', 'active')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        abort_unless($project->tenant?->country_code === $payload['country_code'], 422, 'Project country must match the tenant country.');
        abort_unless($this->access->canAccessCountry($request->user(), $payload['country_code']), 403);

        $project->update($payload);

        return response()->json(['data' => $project->load(['country', 'tenant'])]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        abort_unless($this->access->canAccessProject($request->user(), $project, 'projects.delete'), 403);

        $project->delete();

        return response()->json(status: 204);
    }

    private function normalizeCountryCode(Request $request): void
    {
        if ($request->filled('country_code')) {
            $request->merge(['country_code' => strtoupper($request->input('country_code'))]);
        }
    }
}

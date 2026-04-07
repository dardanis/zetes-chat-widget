<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->whereIn('tenant_id', $request->user()->tenants()->pluck('tenants.id'))
            ->latest('id')
            ->get();

        return response()->json(['data' => $projects]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $isMember = $request->user()->tenants()->whereKey($payload['tenant_id'])->exists();

        abort_unless($isMember, 403);

        $widgetSecret = Str::random(48);

        $project = Project::query()->create([
            'tenant_id' => $payload['tenant_id'],
            'owner_id' => $request->user()->id,
            'name' => $payload['name'],
            'slug' => Str::slug($payload['name']).'-'.Str::lower(Str::random(6)),
            'widget_key' => Str::random(40),
            'widget_secret' => $widgetSecret,
            'widget_secret_hash' => Hash::make($widgetSecret),
        ]);

        return response()->json([
            'data' => $project,
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $isMember = $request->user()->tenants()->whereKey($project->tenant_id)->exists();
        abort_unless($isMember, 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project->update(['name' => $payload['name']]);

        return response()->json(['data' => $project]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $isMember = $request->user()->tenants()->whereKey($project->tenant_id)->exists();
        abort_unless($isMember, 403);

        $project->delete();

        return response()->json(status: 204);
    }
}



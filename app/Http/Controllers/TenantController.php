<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->tenants()->latest('tenants.id')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::query()->create(['name' => $payload['name']]);
        $tenant->users()->attach($request->user()->id, ['role' => 'owner']);

        return response()->json(['data' => $tenant], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->exists(), 403);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $tenant->update($payload);

        return response()->json(['data' => $tenant]);
    }

    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($request->user()->tenants()->whereKey($tenant->id)->exists(), 403);

        $tenant->delete();

        return response()->json(status: 204);
    }
}


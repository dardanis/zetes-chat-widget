<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), 'tenants.view'), 403);

        $search = trim((string) $request->query('search', $request->query('q', '')));

        $query = $this->access->scopeTenantsFor(
            $request->user(),
            Tenant::query()->with('country')
        );

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('country_code', 'like', "%{$search}%")
                    ->orWhereHas('country', function ($query) use ($search): void {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json(['data' => $query->latest('id')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), 'tenants.create'), 403);
        $this->normalizeCountryCode($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')->where('status', 'active')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        abort_unless($this->access->canAccessCountry($request->user(), $payload['country_code']), 403);

        $tenant = Tenant::query()->create([
            'name' => $payload['name'],
            'country_code' => $payload['country_code'],
            'status' => $payload['status'] ?? 'active',
        ]);
        $tenant->users()->attach($request->user()->id, ['role' => 'owner']);

        return response()->json(['data' => $tenant->load('country')], 201);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($this->access->canAccessTenant($request->user(), $tenant, 'tenants.update'), 403);
        $this->normalizeCountryCode($request);

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')->where('status', 'active')],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        abort_unless($this->access->canAccessCountry($request->user(), $payload['country_code']), 403);

        $tenant->update($payload);

        return response()->json(['data' => $tenant->load('country')]);
    }

    public function destroy(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($this->access->canAccessTenant($request->user(), $tenant, 'tenants.delete'), 403);

        $tenant->delete();

        return response()->json(status: 204);
    }

    private function normalizeCountryCode(Request $request): void
    {
        if ($request->filled('country_code')) {
            $request->merge(['country_code' => strtoupper($request->input('country_code'))]);
        }
    }
}

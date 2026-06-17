<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->isAdmin($request->user()), 403);

        $users = User::query()
            ->with('countries')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->serializeUser($user));

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->access->isAdmin($request->user()), 403);

        $payload = $this->validatedPayload($request);

        $user = DB::transaction(function () use ($payload): User {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => strtolower($payload['email']),
                'password' => $payload['password'],
                'role' => $payload['role'],
                'status' => $payload['status'] ?? 'active',
            ]);

            $this->syncCountries($user, $payload['country_codes'] ?? []);

            return $user->load('countries');
        });

        return response()->json(['data' => $this->serializeUser($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($this->access->isAdmin($request->user()), 403);

        $payload = $this->validatedPayload($request, $user);

        DB::transaction(function () use ($payload, $user): void {
            $user->fill(collect($payload)->only(['name', 'email', 'role', 'status'])->all());
            $user->save();

            if (array_key_exists('country_codes', $payload)) {
                $this->syncCountries($user, $payload['country_codes']);
            }
        });

        return response()->json(['data' => $this->serializeUser($user->fresh()->load('countries'))]);
    }

    public function changePassword(Request $request, User $user): JsonResponse
    {
        abort_unless($this->access->isAdmin($request->user()), 403);

        $payload = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'revoke_tokens' => ['sometimes', 'boolean'],
        ]);

        $user->update(['password' => $payload['password']]);

        if (($payload['revoke_tokens'] ?? true) && Schema::hasTable('personal_access_tokens')) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Password changed.']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($this->access->isAdmin($request->user()), 403);
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');

        $user->delete();

        return response()->json(status: 204);
    }

    private function validatedPayload(Request $request, ?User $user = null): array
    {
        $this->normalizeCountryCodes($request);

        return $request->validate([
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'sometimes' : 'required', 'string', 'min:8'],
            'role' => [$user ? 'sometimes' : 'required', Rule::in(array_keys(config('permissions.roles')))],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'country_codes' => ['array'],
            'country_codes.*' => ['string', 'size:2', Rule::exists('countries', 'code')->where('status', 'active')],
        ]);
    }

    private function normalizeCountryCodes(Request $request): void
    {
        if (! $request->has('country_codes') || ! is_array($request->input('country_codes'))) {
            return;
        }

        $request->merge([
            'country_codes' => collect($request->input('country_codes'))
                ->map(fn (string $countryCode): string => strtoupper($countryCode))
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    private function syncCountries(User $user, array $countryCodes): void
    {
        $user->countries()->sync($countryCodes);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'country_codes' => $user->countries->pluck('code')->sort()->values()->all(),
            'countries' => $user->countries->sortBy('name')->values(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($this->access->can($request->user(), 'countries.view'), 403);

        $query = Country::query()->orderBy('name');

        if (! $this->access->isAdmin($request->user())) {
            $query->whereIn('code', $this->access->countryCodes($request->user()));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function update(Request $request, Country $country): JsonResponse
    {
        abort_unless($this->access->isAdmin($request->user()), 403);

        $payload = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
        ]);

        $country->update($payload);

        return response()->json(['data' => $country]);
    }
}

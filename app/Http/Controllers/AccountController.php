<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function changePassword(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        abort_unless(Hash::check($payload['current_password'], $request->user()->password), 422, 'The current password is incorrect.');

        $request->user()->update([
            'password' => $payload['password'],
        ]);

        return response()->json(['message' => 'Password changed.']);
    }
}

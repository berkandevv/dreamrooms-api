<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Registra clientes activos; los roles avanzados los gestionaremos cuando activemos auth
        $user = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => 'customer'])->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'status' => 'active',
        ]);

        $user->load('role');

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }
}

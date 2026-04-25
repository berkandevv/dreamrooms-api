<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a customer account
     *
     * Creates an active customer user and returns a bearer token
     */
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

    /**
     * Login with email and password
     *
     * Returns a bearer token for active users with valid credentials
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Genera un token solo para usuarios API activos con credenciales válidas
        $user = User::query()
            ->with('role')
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This user account is not active.'],
            ]);
        }

        if ($user->hasRole('admin')) {
            abort(403, 'Admin users cannot authenticate through the API.');
        }

        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Show the authenticated user
     *
     * Returns the profile associated with the bearer token
     */
    public function me(Request $request): UserResource
    {
        $user = $request->user();
        $user->loadMissing('role');

        return new UserResource($user);
    }

    /**
     * Logout the current token
     *
     * Revokes only the bearer token used for the current request
     */
    public function logout(Request $request)
    {
        // Revoca solo el token actual enviado en el header Authorization
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}

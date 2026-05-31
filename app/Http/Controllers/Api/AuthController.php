<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a customer or owner account
     *
     * Creates an active API user and returns a bearer token
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'account_type' => ['nullable', 'string', Rule::in(['customer', 'owner'])],
        ]);

        $accountType = $validated['account_type'] ?? 'customer';

        // Solo permite alta pública de roles API no administrativos.
        $user = User::query()->create([
            'role_id' => Role::query()->firstOrCreate(['name' => $accountType])->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'status' => 'active',
        ]);

        $user->load('role');

        return response()->json([
            'data' => UserResource::make($user),
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
            'data' => UserResource::make($user),
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

        return UserResource::make($user);
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

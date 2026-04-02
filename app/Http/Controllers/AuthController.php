<?php

namespace App\Http\Controllers;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:3'],
        ]);

        // Register on Cognee first
        $this->cogneeService->register($validated['email'], $validated['password']);

        // Login on Cognee to get auth token
        $loginResult = $this->cogneeService->login($validated['email'], $validated['password']);

        // Create local user
        $user = User::create([
            'name' => explode('@', $validated['email'])[0],
            'email' => $validated['email'],
            'password' => $validated['password'], // hashed via cast
            'cognee_token' => $loginResult['token'],
            'cognee_password' => $validated['password'],
        ]);

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Authenticate with Cognee to get token
        $loginResult = $this->cogneeService->login($validated['email'], $validated['password']);

        // Find local user and verify password
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw new CogneeApiException(
                statusCode: 401,
                cogneeDetail: 'Invalid local credentials',
                message: 'Invalid credentials',
            );
        }

        // Update Cognee token
        $user->update([
            'cognee_token' => $loginResult['token'],
            'cognee_password' => $validated['password'],
        ]);

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $cogneeProfile = [];
        try {
            $cogneeProfile = $this->withCogneeSession($user, $this->cogneeService, function (string $token) {
                return $this->cogneeService->me($token);
            });
        } catch (CogneeApiException) {
            // Cognee may be unavailable or unrecoverable; keep local user available.
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'cognee' => $cogneeProfile,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $shouldLogoutCognee = $user->tokens()->count() <= 1;

        if ($shouldLogoutCognee && $user->cognee_token) {
            try {
                $this->cogneeService->logout($user->cognee_token);
            } catch (CogneeApiException) {
                // Don't block logout if Cognee call fails
            }
        }

        $user->currentAccessToken()?->delete();

        if ($shouldLogoutCognee) {
            $user->update([
                'cognee_token' => null,
                'cognee_password' => null,
            ]);
        }

        return response()->json(['message' => 'Logged out']);
    }
}

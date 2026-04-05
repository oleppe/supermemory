<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SupermemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly SupermemoryService $supermemoryService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:3'],
        ]);

        $user = User::create([
            'name' => explode('@', $validated['email'])[0],
            'email' => $validated['email'],
            'password' => $validated['password'],
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

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

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

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
            'supermemory' => [
                'configured' => $this->supermemoryService->isConfigured(),
                'container_tag' => $this->supermemoryContainerTag($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->authenticatedUser($request)->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }
}

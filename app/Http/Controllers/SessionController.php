<?php

namespace App\Http\Controllers;

use App\Exceptions\CogneeApiException;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        $cognee = [
            'authenticated' => false,
            'status' => $user->cognee_token || $user->cognee_password ? 'unknown' : 'missing',
            'profile' => null,
            'can_restore_automatically' => (bool) $user->cognee_password,
        ];

        if ($user->cognee_token || $user->cognee_password) {
            try {
                $profile = $this->withCogneeSession($user, $this->cogneeService, function (string $token) {
                    return $this->cogneeService->me($token);
                });
                $cognee = [
                    'authenticated' => true,
                    'status' => 'active',
                    'profile' => $profile,
                    'can_restore_automatically' => true,
                ];
            } catch (CogneeApiException $exception) {
                if ($exception->statusCode === 401) {
                    $cognee['status'] = $this->isRecoveryUnavailable($exception)
                        ? 'requires_credentials'
                        : 'expired';
                } else {
                    $cognee['status'] = 'unavailable';
                }

                $cognee['detail'] = $exception->cogneeDetail;
            }
        }

        return response()->json([
            'auth' => [
                'authenticated' => true,
                'user_id' => $user->id,
            ],
            'cognee' => $cognee,
        ]);
    }

    public function restore(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:3'],
        ]);

        $password = $validated['password'] ?? null;

        if (! $user->cognee_password && ! $password) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'password' => ['The password field is required to restore the Cognee session for this account.'],
                ],
            ], 422);
        }

        $token = $password
            ? $this->cogneeService->authenticateUser($user, $password)
            : $this->cogneeService->restoreUserSession($user);

        $profile = $this->cogneeService->me($token);

        return response()->json([
            'message' => 'Cognee session restored',
            'cognee' => [
                'authenticated' => true,
                'status' => 'active',
                'profile' => $profile,
                'can_restore_automatically' => true,
            ],
        ]);
    }

    private function isRecoveryUnavailable(CogneeApiException $exception): bool
    {
        return is_array($exception->cogneeDetail)
            && ($exception->cogneeDetail['code'] ?? null) === 'COGNEE_SESSION_RECOVERY_UNAVAILABLE';
    }
}

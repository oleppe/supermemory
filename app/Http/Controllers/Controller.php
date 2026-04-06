<?php

namespace App\Http\Controllers;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use App\Services\CogneeService;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function authenticatedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    protected function requireCogneeToken(User $user): string
    {
        if (! $user->cognee_token) {
            throw CogneeApiException::sessionExpired();
        }

        return $user->cognee_token;
    }

    protected function withCogneeSession(User $user, CogneeService $cogneeService, callable $callback): mixed
    {
        $token = $user->cognee_token ?: $cogneeService->restoreUserSession($user);

        try {
            return $callback($token);
        } catch (CogneeApiException $exception) {
            if ($exception->statusCode !== 401) {
                throw $exception;
            }

            $token = $cogneeService->restoreUserSession($user->fresh());

            return $callback($token);
        }
    }
}

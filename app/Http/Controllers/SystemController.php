<?php

namespace App\Http\Controllers;

use App\Exceptions\CogneeApiException;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function health(): JsonResponse
    {
        try {
            $health = $this->cogneeService->health();

            return response()->json([
                'laravel' => 'ok',
                'cognee' => [
                    'reachable' => true,
                    'health' => $health,
                ],
            ]);
        } catch (CogneeApiException $exception) {
            return response()->json([
                'laravel' => 'ok',
                'cognee' => [
                    'reachable' => false,
                    'error' => $exception->cogneeDetail,
                ],
            ], 503);
        }
    }

    public function connection(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $result = $this->withCogneeSession($user, $this->cogneeService, function (string $token) {
            return $this->cogneeService->checkConnection($token);
        });

        return response()->json([
            'data' => $result,
        ]);
    }
}

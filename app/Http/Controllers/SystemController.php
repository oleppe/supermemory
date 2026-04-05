<?php

namespace App\Http\Controllers;

use App\Exceptions\SupermemoryApiException;
use App\Services\SupermemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function __construct(
        private readonly SupermemoryService $supermemoryService,
    ) {}

    public function health(): JsonResponse
    {
        try {
            $health = $this->supermemoryService->health();

            return response()->json([
                'laravel' => 'ok',
                'supermemory' => [
                    'reachable' => true,
                    'health' => $health,
                ],
            ]);
        } catch (SupermemoryApiException $exception) {
            return response()->json([
                'laravel' => 'ok',
                'supermemory' => [
                    'reachable' => false,
                    'error' => $exception->detail,
                ],
            ], 503);
        }
    }

    public function connection(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $result = $this->supermemoryService->checkConnection($this->supermemoryContainerTag($user));

        return response()->json([
            'data' => $result,
        ]);
    }
}

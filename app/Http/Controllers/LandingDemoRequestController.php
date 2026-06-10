<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLandingDemoRequest;
use App\Mail\LandingDemoRequestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class LandingDemoRequestController extends Controller
{
    public function __invoke(StoreLandingDemoRequest $request): JsonResponse
    {
        Mail::to((string) config('services.landing.recipient'))
            ->send(new LandingDemoRequestMail($request->validated()));

        return response()->json([
            'message' => 'Demo request received.',
        ], 201);
    }
}

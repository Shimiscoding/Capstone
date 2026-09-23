<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfficerLocationRequest;
use App\Services\OfficerLocationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class OfficerLocationController extends Controller
{
    public function store(StoreOfficerLocationRequest $request, OfficerLocationService $service): JsonResponse
    {
        try {
            $location = $service->update($request->user(), $request->validated());

            return response()->json(['message' => 'Location updated.', 'recorded_at' => $location->recorded_at]);
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }
            Log::error('Officer location update failed.', ['user_id' => $request->user()->id, 'exception' => $exception]);

            return response()->json(['message' => 'Unable to update location.'], 500);
        }
    }
}

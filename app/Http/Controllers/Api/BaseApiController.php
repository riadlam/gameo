<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseApiController extends Controller
{
    protected function respondResource(
        JsonResource $resource,
        string $message = 'Request successful.',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource->resolve(),
        ], $status);
    }

    protected function respondCollection(
        ResourceCollection $collection,
        string $message = 'Request successful.',
        int $status = 200
    ): JsonResponse {
        $resolved = $collection->resolve();

        return response()->json([
            'success' => true,
            'message' => $message,
            ...$resolved,
        ], $status);
    }

    protected function respondSuccess(
        array $data = [],
        string $message = 'Request successful.',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            ...$data,
        ], $status);
    }

    protected function respondDeleted(string $message = 'Deleted successfully.'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    protected function ensureOwner(int $expectedUserId, int $actualUserId): void
    {
        if ($expectedUserId !== $actualUserId) {
            throw new AuthorizationException('You are not allowed to access this resource.');
        }
    }
}


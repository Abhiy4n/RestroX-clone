<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells the frontend who is logged in (GET /api/user).
 */
class CurrentUserController extends Controller
{
    /**
     * Return the logged-in user's details.
     *
     * The frontend calls this when the page loads to check if someone is logged in.
     * The route's auth:sanctum middleware already blocks visitors who aren't logged in.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // A deactivated account might still have an old session, so block it here too.
        abort_unless($user?->is_active, 403, 'This account is unavailable.');

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'message' => 'Authenticated account.',
        ]);
    }
}

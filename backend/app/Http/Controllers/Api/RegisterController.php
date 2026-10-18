<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Handles sign-up (POST /api/register).
 */
class RegisterController extends Controller
{
    /**
     * Create a new user account.
     *
     * By the time this runs, RegisterRequest has already cleaned up and validated the input.
     * RegisterUser saves the user and emails them a verification link. They can't log in
     * until they click it.
     */
    public function store(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->validated());

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'message' => 'Your account was created. Verify your email address before signing in.',
        ], 201);
    }
}

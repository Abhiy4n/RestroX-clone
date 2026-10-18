<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles the link in the verification email (GET /api/email/verify/{id}/{hash}).
 */
class VerifyEmailController extends Controller
{
    /**
     * Mark the user's email address as verified.
     *
     * The route's `signed` middleware has already checked that the link wasn't edited and
     * hasn't expired. Here we also check that the hash in the link matches the user's
     * current email address.
     */
    public function show(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        // hash_equals compares in constant time, so attackers can't guess the hash
        // by measuring how long the comparison takes.
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new NotFoundHttpException;
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            Log::info('User email verified.', ['user_id' => $user->getKey()]);
        }

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'message' => 'Email address verified. You can now sign in.',
        ]);
    }
}

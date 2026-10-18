<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\AuthenticateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

/**
 * Handles login with email and password (POST /api/login).
 */
class LoginController extends Controller
{
    /**
     * Log the user in and start their session.
     *
     * LoginRequest checks the input first. AuthenticateUser checks the password and the
     * account status, and throws a validation error if anything is wrong.
     */
    public function store(LoginRequest $request, AuthenticateUser $authenticateUser): JsonResponse
    {
        $user = $authenticateUser->handle(
            $request->email(),
            $request->password(),
            $request->boolean('remember'),
        );

        // Give the user a brand-new session ID, so a session ID an attacker obtained
        // before login (called "session fixation") becomes useless.
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'message' => 'Signed in successfully.',
        ]);
    }
}

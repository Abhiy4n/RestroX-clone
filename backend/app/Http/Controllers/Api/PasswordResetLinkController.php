<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendPasswordResetLinkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

/**
 * Handles "Forgot password?" (POST /api/forgot-password).
 */
class PasswordResetLinkController extends Controller
{
    /**
     * Email the user a link to reset their password.
     *
     * The reply is the same whether or not the email belongs to an account. Otherwise
     * anyone could use this form to find out who has an account.
     */
    public function store(SendPasswordResetLinkRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->validated());

        // Log a hash of the email instead of the email itself, to keep personal data out of logs.
        Log::info('Password reset link requested.', [
            'email_hash' => hash('sha256', (string) $request->validated('email')),
            'status' => $status,
        ]);

        return response()->json([
            'user' => null,
            'message' => 'If an account exists for that email, a password reset link will be sent.',
        ], 202);
    }
}

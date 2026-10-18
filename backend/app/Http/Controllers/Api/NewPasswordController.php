<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Handles setting a new password from the reset email (POST /api/reset-password).
 */
class NewPasswordController extends Controller
{
    /**
     * Save the user's new password if their reset token is valid.
     *
     * Password::reset checks the token, then runs the function below to save the password.
     */
    public function store(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->validated(),
            function ($user, string $password): void {
                // Changing remember_token logs out every device that used "Remember me".
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                Log::info('User password reset.', ['user_id' => $user->getKey()]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'user' => null,
                'message' => 'The password reset token is invalid or expired.',
            ], 422);
        }

        return response()->json(['user' => null, 'message' => 'Password reset successfully.']);
    }
}

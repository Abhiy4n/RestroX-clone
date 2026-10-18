<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Handles logout (POST /api/logout).
 */
class LogoutController extends Controller
{
    /**
     * Log the user out of this device and throw away their session.
     */
    public function destroy(Request $request): JsonResponse
    {
        Log::info('User logged out.', ['user_id' => $request->user()?->getKey()]);
        Auth::guard('web')->logout();

        // Delete everything stored in the session and issue a new CSRF token,
        // so nothing from the old login can be reused.
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['user' => null, 'message' => 'Logged out successfully.']);
    }
}

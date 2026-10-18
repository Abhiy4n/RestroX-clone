<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shows the devices the user is logged in on (GET /api/sessions).
 */
class UserSessionController extends Controller
{
    /**
     * List the logged-in user's active sessions, most recently used first.
     *
     * Each session is one browser or device. Laravel stores them in the `sessions` table,
     * so this only works with SESSION_DRIVER=database.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user?->is_active, 403, 'This account is unavailable.');

        // Laravel only deletes old sessions now and then, so the table can contain expired
        // rows. Only keep sessions used within the session lifetime (SESSION_LIFETIME minutes).
        $sessions = DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $user->getKey())
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())
            ->orderByDesc('last_activity')
            ->get();

        return response()->json([
            'sessions' => UserSessionResource::collection($sessions)->resolve($request),
            'message' => 'Active sessions.',
        ]);
    }
}

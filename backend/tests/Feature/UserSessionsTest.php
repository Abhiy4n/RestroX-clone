<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
    }

    public function test_guests_cannot_list_sessions(): void
    {
        $this->getJson('/api/sessions')->assertUnauthorized();
    }

    public function test_lists_only_the_users_active_sessions_and_flags_the_current_device(): void
    {
        $user = User::factory()->create();
        $currentSessionId = $this->createSession($user, ipAddress: '203.0.113.10', userAgent: 'Firefox on Linux');
        $otherSessionId = $this->createSession($user, ipAddress: '198.51.100.7', userAgent: 'Safari on iPhone', minutesAgo: 30);
        $this->createSession($user, minutesAgo: (int) config('session.lifetime') + 5);
        $this->createSession(User::factory()->create());

        $response = $this->listSessions($user, $currentSessionId)->assertOk();

        $response->assertJsonCount(2, 'sessions')
            ->assertJsonPath('sessions.0.id', hash('sha256', $currentSessionId))
            ->assertJsonPath('sessions.0.ip_address', '203.0.113.10')
            ->assertJsonPath('sessions.0.user_agent', 'Firefox on Linux')
            ->assertJsonPath('sessions.0.is_current_device', true)
            ->assertJsonPath('sessions.1.id', hash('sha256', $otherSessionId))
            ->assertJsonPath('sessions.1.is_current_device', false);
        $this->assertStringNotContainsString($otherSessionId, $response->getContent());
    }

    public function test_inactive_users_cannot_list_sessions(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $sessionId = $this->createSession($user);

        $this->listSessions($user, $sessionId)->assertForbidden();
    }

    private function createSession(
        User $user,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Test Browser',
        int $minutesAgo = 0,
    ): string {
        $sessionId = Str::random(40);

        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->getKey(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);

        return $sessionId;
    }

    private function listSessions(User $user, string $sessionId): TestResponse
    {
        return $this->actingAs($user)
            ->withHeader('Origin', 'http://localhost:5173')
            ->withCredentials()
            ->withCookie((string) config('session.cookie'), $sessionId)
            ->getJson('/api/sessions');
    }
}

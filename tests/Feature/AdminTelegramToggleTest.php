<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTelegramToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_telegram_alerts_and_the_value_is_persisted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['telegram_notifications_enabled' => false]);

        $response = $this->actingAs($admin, 'sanctum')->postJson(
            "/api/admin/users/{$target->id}/settings",
            ['telegram_notifications_enabled' => true]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('telegram_notifications_enabled', true)
            ->assertJsonPath('updated_user.telegram_notifications_enabled', true);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'telegram_notifications_enabled' => 1,
        ]);
    }
}

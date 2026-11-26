<?php

namespace Tests\Feature;

use App\Models\TeamSummaryNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanySummaryNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_save_and_update_company_summary_notes(): void
    {
        $user = User::factory()->create();
        $payload = [
            'year' => 2026,
            'month' => 11,
            'week_number' => 1,
            'what_worked' => 'Clear handoffs',
            'what_didnt_work' => 'Late updates',
            'what_to_improve' => 'Share progress earlier',
            'what_is_next' => 'Review the process',
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/company-summary/notes', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('note.what_worked', 'Clear handoffs');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/company-summary/notes', [
                'year' => 2026,
                'month' => 11,
                'week_number' => 1,
                'what_worked' => 'Updated handoffs',
            ])
            ->assertOk()
            ->assertJsonPath('note.what_worked', 'Updated handoffs');

        $this->assertSame(1, TeamSummaryNote::count());
        $this->assertDatabaseHas('team_summary_notes', [
            'year' => 2026,
            'month' => 11,
            'week_number' => 1,
            'what_worked' => 'Updated handoffs',
            'what_didnt_work' => 'Late updates',
        ]);
    }
}

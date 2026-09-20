<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetPlacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_place_a_bet_from_dashboard_api(): void
    {
        $user = User::factory()->create(['wallet_balance' => 2500]);

        $response = $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => 1,
            'numbers' => [1, 7, 14, 22, 31, 45],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Bet placed successfully.');

        $this->assertDatabaseHas('bets', [
            'user_id' => $user->id,
            'draw_id' => 1,
            'status' => 'pending',
        ]);

        $this->assertEquals(2450.0, (float) $user->fresh()->wallet_balance);
    }

    public function test_bet_requires_exact_number_count(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => 1,
            'numbers' => [1, 2, 3],
        ]);

        $response->assertStatus(422);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Draws;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BetPlacementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Before DESAWAR 05:00 AM so 40-min cutoff window is still open
        Carbon::setTestNow(Carbon::parse('2026-09-20 03:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function seedLiveBoards(): int
    {
        $today = now('Asia/Kolkata')->toDateString();
        $yesterday = now('Asia/Kolkata')->subDay()->toDateString();

        Cache::put('satta_king_fast.board.live', [
            'rows' => [
                [
                    'code' => 'DESAWAR',
                    'name' => 'DESAWAR',
                    'time' => '05:00 AM',
                    'chart_url' => 'https://satta-king-fast.com/',
                    'yesterday' => '11',
                    'today' => 'XX',
                    'highlight' => true,
                ],
            ],
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'error' => null,
        ], now()->addMinutes(10));

        Cache::put('satta_kalyan_matka.board.live', [
            'rows' => [
                [
                    'name' => 'KALYAN',
                    'result' => 'XX',
                    'time' => '04:15 PM',
                    'chart_url' => 'https://sattakalyanmatka.net/',
                ],
            ],
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'error' => null,
        ], now()->addMinutes(10));

        return Draws::marketId('king', 'desawar');
    }

    public function test_user_can_place_a_bet_on_satta_king_market(): void
    {
        $drawId = $this->seedLiveBoards();
        $user = User::factory()->create(['wallet_balance' => 2500]);

        $response = $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'king',
            'bet_type' => 'number',
            'numbers' => [45],
            'amount' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Bet placed successfully.')
            ->assertJsonPath('bet.board', 'king');

        $this->assertDatabaseHas('bets', [
            'user_id' => $user->id,
            'draw_id' => $drawId,
            'status' => 'pending',
        ]);

        $this->assertEquals(2490.0, (float) $user->fresh()->wallet_balance);
    }

    public function test_user_can_place_a_bet_on_kalyan_matka_market(): void
    {
        $this->seedLiveBoards();
        $drawId = Draws::marketId('matka', 'kalyan');
        $user = User::factory()->create(['wallet_balance' => 100]);

        $response = $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'matka',
            'bet_type' => 'jodi',
            'numbers' => [7],
            'amount' => 20,
        ]);

        $response->assertCreated();
        $this->assertEquals(80.0, (float) $user->fresh()->wallet_balance);
    }

    public function test_betting_closes_forty_minutes_before_result(): void
    {
        $this->seedLiveBoards();
        // DESAWAR 05:00 → cutoff 04:20; freeze at 04:30
        Carbon::setTestNow(Carbon::parse('2026-09-20 04:30:00', 'Asia/Kolkata'));

        $drawId = Draws::marketId('king', 'desawar');
        $user = User::factory()->create(['wallet_balance' => 100]);

        $response = $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'king',
            'bet_type' => 'number',
            'numbers' => [12],
            'amount' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_bet_requires_exact_number_count(): void
    {
        $drawId = $this->seedLiveBoards();
        $user = User::factory()->create(['wallet_balance' => 100]);

        $response = $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'numbers' => [1, 2, 3],
            'amount' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_dashboard_lists_king_and_matka_markets(): void
    {
        $this->seedLiveBoards();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard');

        $response->assertOk();
        $draws = collect($response->json('upcoming_draws'));
        $this->assertTrue($draws->contains(fn ($d) => ($d['board'] ?? null) === 'king'));
        $this->assertTrue($draws->contains(fn ($d) => ($d['board'] ?? null) === 'matka'));
    }
}

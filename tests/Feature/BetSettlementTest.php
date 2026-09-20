<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\User;
use App\Services\BetSettlementService;
use App\Support\Draws;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BetSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function seedKingBoard(string $todayResult = 'XX'): int
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
                    'today' => $todayResult,
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

    protected function seedMatkaResult(string $result): int
    {
        $today = now('Asia/Kolkata')->toDateString();
        $yesterday = now('Asia/Kolkata')->subDay()->toDateString();

        Cache::put('satta_kalyan_matka.board.live', [
            'rows' => [
                [
                    'name' => 'KALYAN',
                    'result' => $result,
                    'time' => '04:15 PM',
                    'chart_url' => 'https://sattakalyanmatka.net/',
                ],
            ],
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'error' => null,
        ], now()->addMinutes(10));

        return Draws::marketId('matka', 'kalyan');
    }

    public function test_winning_king_bet_credits_nine_times(): void
    {
        $drawId = $this->seedKingBoard('XX');
        $user = User::factory()->create(['wallet_balance' => 100]);

        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'king',
            'bet_type' => 'number',
            'numbers' => [45],
            'amount' => 10,
        ])->assertCreated();

        $this->assertEquals(90.0, (float) $user->fresh()->wallet_balance);

        $this->seedKingBoard('45');
        $stats = app(BetSettlementService::class)->settle();

        $this->assertSame(1, $stats['won']);
        $this->assertEquals(180.0, (float) $user->fresh()->wallet_balance);
        $this->assertSame('won', Bet::first()->status);
    }

    public function test_matka_jodi_win_credits_ninety_times(): void
    {
        $this->seedKingBoard('XX');
        $drawId = $this->seedMatkaResult('XX');
        $user = User::factory()->create(['wallet_balance' => 100]);

        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'matka',
            'bet_type' => 'jodi',
            'numbers' => [47],
            'amount' => 10,
        ])->assertCreated();

        $this->seedMatkaResult('257-47-368');
        $stats = app(BetSettlementService::class)->settle();

        $this->assertSame(1, $stats['won']);
        $this->assertEquals(990.0, (float) $user->fresh()->wallet_balance); // 90 left + 900 win
        $this->assertEquals(900.0, (float) Bet::first()->prize);
    }

    public function test_matka_single_open_settles_on_ank(): void
    {
        $this->seedKingBoard('XX');
        $drawId = $this->seedMatkaResult('XX');
        $user = User::factory()->create(['wallet_balance' => 50]);

        // Open pana 257 → ank 2+5+7=14 → 4
        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'matka',
            'bet_type' => 'single_open',
            'numbers' => [4],
            'amount' => 5,
        ])->assertCreated();

        $this->seedMatkaResult('257-47-368');
        app(BetSettlementService::class)->settle();

        $this->assertSame('won', Bet::first()->status);
        $this->assertEquals(45.0 + 45.0, (float) $user->fresh()->wallet_balance); // 45 after stake + 45 win (5*9)
    }

    public function test_any_amount_from_one_rupee_is_allowed(): void
    {
        $drawId = $this->seedKingBoard('XX');
        $user = User::factory()->create(['wallet_balance' => 20]);

        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'bet_type' => 'number',
            'numbers' => [7],
            'amount' => 1,
        ])->assertCreated();

        $this->assertEquals(19.0, (float) $user->fresh()->wallet_balance);
    }
}

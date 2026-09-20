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
            'rows' => [],
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'error' => null,
        ], now()->addMinutes(10));

        return Draws::marketId('king', 'desawar');
    }

    public function test_winning_bet_credits_nine_times_stake_to_wallet(): void
    {
        $drawId = $this->seedKingBoard('XX');
        $user = User::factory()->create(['wallet_balance' => 100]);

        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'king',
            'numbers' => [45],
            'amount' => 10,
        ])->assertCreated();

        $this->assertEquals(90.0, (float) $user->fresh()->wallet_balance);

        // Result declared as 45 → win 10 × 9 = 90 credited
        $this->seedKingBoard('45');
        $stats = app(BetSettlementService::class)->settle();

        $this->assertSame(1, $stats['won']);
        $this->assertEquals(180.0, (float) $user->fresh()->wallet_balance);

        $bet = Bet::first();
        $this->assertSame('won', $bet->status);
        $this->assertEquals(90.0, (float) $bet->prize);
        $this->assertSame('45', $bet->result_value);
    }

    public function test_losing_bet_marks_lost_without_credit(): void
    {
        $drawId = $this->seedKingBoard('XX');
        $user = User::factory()->create(['wallet_balance' => 50]);

        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'board' => 'king',
            'numbers' => [12],
            'amount' => 5,
        ])->assertCreated();

        $this->seedKingBoard('99');
        app(BetSettlementService::class)->settle();

        $this->assertEquals(45.0, (float) $user->fresh()->wallet_balance);
        $this->assertSame('lost', Bet::first()->status);
    }

    public function test_any_amount_from_one_rupee_is_allowed(): void
    {
        $drawId = $this->seedKingBoard('XX');
        $user = User::factory()->create(['wallet_balance' => 20]);

        $this->actingAs($user)->postJson('/api/bets', [
            'draw_id' => $drawId,
            'numbers' => [7],
            'amount' => 1,
        ])->assertCreated();

        $this->assertEquals(19.0, (float) $user->fresh()->wallet_balance);
    }
}

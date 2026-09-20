<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CombinedResultsService;
use App\Support\BetPresenter;
use App\Support\Draws;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CombinedResultsService $api): JsonResponse
    {
        $user = $request->user();
        $bets = $user->bets()->latest()->get();
        $pending = $bets->where('status', 'pending')->values();
        $settled = $bets->whereIn('status', ['won', 'lost', 'refunded'])->values();
        $winsCount = $bets->where('status', 'won')->count();
        $balance = (float) $user->wallet_balance;

        $resultsPayload = $api->toResultsPayload();
        $draws = Draws::all($resultsPayload);
        $openDraws = collect($draws)->where('status', 'open')->values();

        $recentResults = collect($resultsPayload['king_results'] ?? [])
            ->filter(fn ($r) => ($r['is_due'] ?? false) || ($r['is_next_up'] ?? false))
            ->take(4)
            ->merge(
                collect($resultsPayload['matka_results'] ?? [])
                    ->filter(fn ($r) => ($r['is_due'] ?? false) || ($r['is_next_up'] ?? false))
                    ->take(4)
            )
            ->values()
            ->all();

        return response()->json([
            'user' => [
                'name' => $user->name,
                'mobile' => $user->country_code.' '.$user->mobile,
            ],
            'wallet_balance' => $balance,
            'betting' => [
                'min_amount' => (float) config('betting.min_amount', 1),
                'max_amount' => (float) config('betting.max_amount', 100000),
                'king' => config('betting.king'),
                'matka_types' => config('betting.matka.types'),
                'ticket_price' => (float) config('betting.ticket_price', 1),
                'prize_multiplier' => (float) config('betting.king.multiplier', 90),
                'pick_count' => 1,
                'min_number' => 0,
                'max_number' => 99,
            ],
            'stats' => [
                [
                    'key' => 'balance',
                    'label' => 'Wallet',
                    'value' => $balance,
                    'display' => '₹'.number_format($balance, 0),
                    'hint' => 'Available',
                ],
                [
                    'key' => 'active',
                    'label' => 'Running bets',
                    'value' => $pending->count(),
                    'display' => (string) $pending->count(),
                    'hint' => 'Result wait',
                ],
                [
                    'key' => 'wins',
                    'label' => 'Wins',
                    'value' => $winsCount,
                    'display' => (string) $winsCount,
                    'hint' => 'All time',
                ],
                [
                    'key' => 'draws',
                    'label' => 'Open markets',
                    'value' => $openDraws->count(),
                    'display' => (string) $openDraws->count(),
                    'hint' => 'Ready to bet',
                ],
            ],
            'upcoming_draws' => collect($draws)->map(fn ($draw) => [
                'id' => $draw['id'],
                'board' => $draw['board'],
                'board_label' => $draw['board_label'],
                'market_slug' => $draw['market_slug'],
                'name' => $draw['name'],
                'display_name' => $draw['display_name'],
                'draw_at' => $draw['draw_at'],
                'time_label' => $draw['time_label'],
                'prize' => $draw['prize'],
                'ticket_price' => $draw['ticket_price_display'],
                'ticket_price_value' => $draw['ticket_price'],
                'pick_count' => $draw['pick_count'],
                'min_number' => $draw['min_number'],
                'max_number' => $draw['max_number'],
                'multiplier' => $draw['multiplier'],
                'bet_type' => $draw['bet_type'],
                'bet_types' => $draw['bet_types'],
                'status' => $draw['status'],
                'current_result' => $draw['current_result'],
                'is_due' => $draw['is_due'] ?? false,
                'is_next_up' => $draw['is_next_up'] ?? false,
            ])->values(),
            'active_bets' => $pending->map(fn ($bet) => BetPresenter::toArray($bet))->values(),
            'recent_bets' => $settled->take(10)->map(fn ($bet) => BetPresenter::toArray($bet))->values(),
            // legacy key for older UI
            'my_bets' => $bets->take(12)->map(fn ($bet) => BetPresenter::toArray($bet))->values(),
            'recent_results' => $recentResults,
        ]);
    }
}

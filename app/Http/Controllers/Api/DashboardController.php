<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CombinedResultsService;
use App\Support\Draws;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, CombinedResultsService $api): JsonResponse
    {
        $user = $request->user();
        $bets = $user->bets()->latest()->get();
        $pendingCount = $bets->where('status', 'pending')->count();
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

        if ($recentResults === []) {
            $recentResults = collect($resultsPayload['results'] ?? [])
                ->take(8)
                ->values()
                ->all();
        }

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
                // legacy
                'ticket_price' => (float) config('betting.ticket_price', 1),
                'prize_multiplier' => (float) config('betting.king.multiplier', 9),
                'pick_count' => 1,
                'min_number' => 0,
                'max_number' => 99,
            ],
            'stats' => [
                [
                    'key' => 'balance',
                    'label' => 'Wallet Balance',
                    'value' => $balance,
                    'display' => '₹'.number_format($balance, 0),
                    'hint' => 'Available to play',
                ],
                [
                    'key' => 'tickets',
                    'label' => 'Active Bets',
                    'value' => $pendingCount,
                    'display' => (string) $pendingCount,
                    'hint' => 'Pending draws',
                ],
                [
                    'key' => 'wins',
                    'label' => 'Total Wins',
                    'value' => $winsCount,
                    'display' => (string) $winsCount,
                    'hint' => 'All time',
                ],
                [
                    'key' => 'draws',
                    'label' => 'Open Markets',
                    'value' => $openDraws->count(),
                    'display' => (string) $openDraws->count(),
                    'hint' => 'King + Matka',
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
            'my_bets' => $bets->take(8)->map(function ($bet) {
                $digits = match ($bet->bet_type) {
                    'single_open', 'single_close' => 1,
                    'pana_open', 'pana_close' => 3,
                    default => 2,
                };

                return [
                    'id' => $bet->id,
                    'draw_name' => $bet->draw_name,
                    'board' => $bet->board,
                    'bet_type' => $bet->bet_type,
                    'numbers' => $bet->numbers,
                    'numbers_display' => collect($bet->numbers ?? [])
                        ->map(fn ($n) => str_pad((string) $n, $digits, '0', STR_PAD_LEFT))
                        ->all(),
                    'amount' => (float) $bet->amount,
                    'prize' => (float) $bet->prize,
                    'result_value' => $bet->result_value,
                    'status' => $bet->status,
                    'draw_at' => optional($bet->draw_at)?->toIso8601String(),
                    'created_at' => $bet->created_at?->toIso8601String(),
                ];
            })->values(),
            'recent_results' => $recentResults,
        ]);
    }
}

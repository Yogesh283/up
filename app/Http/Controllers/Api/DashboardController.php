<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SattaMatkaApi;
use App\Support\Draws;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, SattaMatkaApi $api): JsonResponse
    {
        $user = $request->user();
        $bets = $user->bets()->latest()->get();
        $pendingCount = $bets->where('status', 'pending')->count();
        $winsCount = $bets->where('status', 'won')->count();
        $balance = (float) $user->wallet_balance;
        $draws = Draws::open();
        $recentResults = collect($api->toResultsPayload($api->board())['results'] ?? [])
            ->take(5)
            ->values()
            ->all();

        return response()->json([
            'user' => [
                'name' => $user->name,
                'mobile' => $user->country_code.' '.$user->mobile,
            ],
            'wallet_balance' => $balance,
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
                    'label' => 'Open Draws',
                    'value' => count($draws),
                    'display' => (string) count($draws),
                    'hint' => 'Ready to bet',
                ],
            ],
            'upcoming_draws' => collect($draws)->map(fn ($draw) => [
                'id' => $draw['id'],
                'name' => $draw['name'],
                'draw_at' => $draw['draw_at'],
                'prize' => $draw['prize'],
                'ticket_price' => $draw['ticket_price_display'],
                'ticket_price_value' => $draw['ticket_price'],
                'pick_count' => $draw['pick_count'],
                'max_number' => $draw['max_number'],
                'status' => $draw['status'],
            ])->values(),
            'my_bets' => $bets->take(5)->map(fn ($bet) => [
                'id' => $bet->id,
                'draw_name' => $bet->draw_name,
                'numbers' => $bet->numbers,
                'amount' => (float) $bet->amount,
                'status' => $bet->status,
                'draw_at' => optional($bet->draw_at)?->toIso8601String(),
                'created_at' => $bet->created_at?->toIso8601String(),
            ])->values(),
            'recent_results' => $recentResults,
        ]);
    }
}

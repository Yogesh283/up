<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\BetPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bets = $user->bets()->latest()->get();
        $items = $bets->map(fn ($bet) => BetPresenter::toArray($bet))->values();

        $totalSpent = (float) $bets->sum('amount');
        $totalWon = (float) $bets->where('status', 'won')->sum('prize');
        $active = $bets->where('status', 'pending')->count();

        return response()->json([
            'summary' => [
                'total_tickets' => $bets->count(),
                'active' => $active,
                'total_spent' => $totalSpent,
                'total_won' => $totalWon,
            ],
            'items' => $items,
            'active' => $items->where('status', 'pending')->values(),
            'won' => $items->where('status', 'won')->values(),
            'lost' => $items->where('status', 'lost')->values(),
            'refunded' => $items->where('status', 'refunded')->values(),
        ]);
    }
}

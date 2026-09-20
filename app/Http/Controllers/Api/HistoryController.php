<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bets = $user->bets()->latest()->get();

        $totalSpent = (float) $bets->sum('amount');
        $totalWon = (float) $bets->where('status', 'won')->sum('prize');

        return response()->json([
            'summary' => [
                'total_tickets' => $bets->count(),
                'total_spent' => $totalSpent,
                'total_won' => $totalWon,
            ],
            'items' => $bets->map(fn ($bet) => [
                'id' => 'TX-'.$bet->id,
                'draw' => $bet->draw_name,
                'numbers' => $bet->numbers,
                'amount' => (float) $bet->amount,
                'status' => $bet->status,
                'prize' => (float) $bet->prize,
                'created_at' => $bet->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}

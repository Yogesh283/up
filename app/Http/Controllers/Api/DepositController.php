<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $balance = (float) $user->wallet_balance;

        return response()->json([
            'balance' => $balance,
            'min_amount' => 100,
            'max_amount' => 50000,
            'quick_amounts' => [100, 200, 500, 1000, 2000],
            'methods' => [
                ['id' => 'upi', 'label' => 'UPI', 'hint' => 'Instant'],
                ['id' => 'card', 'label' => 'Card', 'hint' => 'Visa / Mastercard'],
                ['id' => 'netbanking', 'label' => 'Net Banking', 'hint' => 'All banks'],
            ],
            'recent' => [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:50000',
            'method' => 'required|string|in:upi,card,netbanking',
        ]);

        return response()->json([
            'message' => 'Deposit request created successfully.',
            'transaction' => [
                'id' => 'DP-'.now()->format('YmdHis').random_int(10, 99),
                'amount' => (float) $validated['amount'],
                'method' => $validated['method'],
                'status' => 'processing',
                'created_at' => now()->toIso8601String(),
            ],
            'balance' => (float) $request->user()->wallet_balance,
        ], 201);
    }
}

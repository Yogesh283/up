<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $balance = (float) $user->wallet_balance;

        return response()->json([
            'balance' => $balance,
            'min_amount' => 200,
            'max_amount' => max($balance, 200),
            'methods' => [
                ['id' => 'upi', 'label' => 'UPI', 'hint' => '1–2 hours'],
                ['id' => 'bank', 'label' => 'Bank Transfer', 'hint' => '1–2 days'],
            ],
            'recent' => [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $balance = (float) $user->wallet_balance;

        $validated = $request->validate([
            'amount' => 'required|numeric|min:200',
            'method' => 'required|string|in:upi,bank',
            'account' => 'required|string|min:5|max:100',
        ]);

        $amount = (float) $validated['amount'];
        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance.',
            ]);
        }

        return response()->json([
            'message' => 'Withdrawal request submitted successfully.',
            'transaction' => [
                'id' => 'WD-'.now()->format('YmdHis').random_int(10, 99),
                'amount' => $amount,
                'method' => $validated['method'],
                'status' => 'processing',
                'created_at' => now()->toIso8601String(),
            ],
            'balance' => $balance,
        ], 201);
    }
}

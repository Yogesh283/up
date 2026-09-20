<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'balance' => 2500,
            'min_amount' => 200,
            'max_amount' => 2500,
            'methods' => [
                ['id' => 'upi', 'label' => 'UPI', 'hint' => '1–2 hours'],
                ['id' => 'bank', 'label' => 'Bank Transfer', 'hint' => '1–2 days'],
            ],
            'recent' => [
                [
                    'id' => 'WD-1104',
                    'amount' => 800,
                    'method' => 'UPI',
                    'status' => 'completed',
                    'created_at' => now()->subDays(1)->toIso8601String(),
                ],
                [
                    'id' => 'WD-1101',
                    'amount' => 500,
                    'method' => 'Bank Transfer',
                    'status' => 'processing',
                    'created_at' => now()->subDays(3)->toIso8601String(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:200|max:2500',
            'method' => 'required|string|in:upi,bank',
            'account' => 'required|string|min:5|max:100',
        ]);

        return response()->json([
            'message' => 'Withdrawal request submitted successfully.',
            'transaction' => [
                'id' => 'WD-'.random_int(2000, 9999),
                'amount' => (float) $validated['amount'],
                'method' => $validated['method'],
                'status' => 'processing',
                'created_at' => now()->toIso8601String(),
            ],
        ], 201);
    }
}

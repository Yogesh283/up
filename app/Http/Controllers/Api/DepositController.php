<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'balance' => 2500,
            'min_amount' => 100,
            'max_amount' => 50000,
            'quick_amounts' => [100, 200, 500, 1000, 2000],
            'methods' => [
                ['id' => 'upi', 'label' => 'UPI', 'hint' => 'Instant'],
                ['id' => 'card', 'label' => 'Card', 'hint' => 'Visa / Mastercard'],
                ['id' => 'netbanking', 'label' => 'Net Banking', 'hint' => 'All banks'],
            ],
            'recent' => [
                [
                    'id' => 'DP-2201',
                    'amount' => 500,
                    'method' => 'UPI',
                    'status' => 'completed',
                    'created_at' => now()->subHours(8)->toIso8601String(),
                ],
                [
                    'id' => 'DP-2198',
                    'amount' => 1000,
                    'method' => 'UPI',
                    'status' => 'completed',
                    'created_at' => now()->subDays(1)->toIso8601String(),
                ],
            ],
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
                'id' => 'DP-'.random_int(3000, 9999),
                'amount' => (float) $validated['amount'],
                'method' => $validated['method'],
                'status' => 'processing',
                'created_at' => now()->toIso8601String(),
            ],
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'latest' => [
                'id' => 201,
                'name' => 'Evening Jackpot',
                'drawn_at' => now()->subHours(2)->toIso8601String(),
                'numbers' => [7, 14, 22, 31, 45, 9],
                'prize' => '₹10,00,000',
                'winners' => 4,
            ],
            'results' => [
                [
                    'id' => 201,
                    'name' => 'Evening Jackpot',
                    'drawn_at' => now()->subHours(2)->toIso8601String(),
                    'numbers' => [7, 14, 22, 31, 45, 9],
                    'prize' => '₹10,00,000',
                ],
                [
                    'id' => 200,
                    'name' => 'Lucky 6',
                    'drawn_at' => now()->subHours(10)->toIso8601String(),
                    'numbers' => [2, 9, 16, 24, 38, 41],
                    'prize' => '₹2,50,000',
                ],
                [
                    'id' => 199,
                    'name' => 'Morning Draw',
                    'drawn_at' => now()->subDay()->toIso8601String(),
                    'numbers' => [4, 13, 21, 29, 35, 48],
                    'prize' => '₹5,00,000',
                ],
            ],
        ]);
    }
}

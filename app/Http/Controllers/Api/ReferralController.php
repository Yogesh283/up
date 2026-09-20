<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = strtoupper(Str::substr(md5($user->id.$user->mobile), 0, 8));

        return response()->json([
            'code' => $code,
            'share_link' => url('/register?ref='.$code),
            'stats' => [
                'invited' => 8,
                'joined' => 5,
                'earned' => 250,
            ],
            'referrals' => [
                [
                    'name' => 'Amit S.',
                    'mobile' => '+91 ****3210',
                    'status' => 'joined',
                    'reward' => 50,
                    'joined_at' => now()->subDays(2)->toIso8601String(),
                ],
                [
                    'name' => 'Priya K.',
                    'mobile' => '+91 ****8871',
                    'status' => 'joined',
                    'reward' => 50,
                    'joined_at' => now()->subDays(5)->toIso8601String(),
                ],
                [
                    'name' => 'Rahul M.',
                    'mobile' => '+91 ****4422',
                    'status' => 'pending',
                    'reward' => 0,
                    'joined_at' => now()->subDays(7)->toIso8601String(),
                ],
            ],
        ]);
    }
}

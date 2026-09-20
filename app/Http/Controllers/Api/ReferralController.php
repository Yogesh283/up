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
                'invited' => 0,
                'joined' => 0,
                'earned' => 0,
            ],
            'referrals' => [],
        ]);
    }
}

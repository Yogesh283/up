<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Support\Draws;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BetController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draw_id' => 'required|integer',
            'numbers' => 'required|array',
            'numbers.*' => 'integer|min:1|max:49',
        ]);

        $draw = Draws::find((int) $validated['draw_id']);

        if (! $draw || ($draw['status'] ?? null) !== 'open') {
            throw ValidationException::withMessages([
                'draw_id' => 'Selected draw is not available.',
            ]);
        }

        $numbers = collect($validated['numbers'])
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $pickCount = $draw['pick_count'];

        if (count($numbers) !== $pickCount) {
            throw ValidationException::withMessages([
                'numbers' => "Please select exactly {$pickCount} numbers.",
            ]);
        }

        foreach ($numbers as $number) {
            if ($number < 1 || $number > $draw['max_number']) {
                throw ValidationException::withMessages([
                    'numbers' => "Numbers must be between 1 and {$draw['max_number']}.",
                ]);
            }
        }

        $amount = (float) $draw['ticket_price'];
        $user = $request->user();

        if ((float) $user->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance. Please deposit first.',
            ]);
        }

        $bet = DB::transaction(function () use ($user, $draw, $numbers, $amount) {
            $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();

            if ((float) $lockedUser->wallet_balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient wallet balance. Please deposit first.',
                ]);
            }

            $lockedUser->wallet_balance = (float) $lockedUser->wallet_balance - $amount;
            $lockedUser->save();

            return Bet::create([
                'user_id' => $lockedUser->id,
                'draw_id' => $draw['id'],
                'draw_name' => $draw['name'],
                'numbers' => $numbers,
                'amount' => $amount,
                'status' => 'pending',
                'prize' => 0,
                'draw_at' => $draw['draw_at'],
            ]);
        });

        $user->refresh();

        return response()->json([
            'message' => 'Bet placed successfully.',
            'bet' => [
                'id' => $bet->id,
                'draw_name' => $bet->draw_name,
                'numbers' => $bet->numbers,
                'amount' => (float) $bet->amount,
                'status' => $bet->status,
                'draw_at' => optional($bet->draw_at)?->toIso8601String(),
                'created_at' => $bet->created_at?->toIso8601String(),
            ],
            'wallet_balance' => (float) $user->wallet_balance,
        ], 201);
    }
}

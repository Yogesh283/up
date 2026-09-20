<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bet;
use App\Services\CombinedResultsService;
use App\Support\Draws;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BetController extends Controller
{
    public function store(Request $request, CombinedResultsService $results): JsonResponse
    {
        $minAmount = (float) config('betting.min_amount', 1);
        $maxAmount = (float) config('betting.max_amount', 100000);

        $validated = $request->validate([
            'draw_id' => 'required|integer',
            'board' => 'nullable|in:king,matka',
            'bet_type' => 'nullable|string|max:32',
            'numbers' => 'required|array|min:1|max:1',
            'numbers.*' => 'integer|min:0|max:999',
            'amount' => "required|numeric|min:{$minAmount}|max:{$maxAmount}",
        ]);

        $payload = $results->toResultsPayload(settleBets: false);
        $draw = Draws::find((int) $validated['draw_id'], $payload);

        if (! $draw) {
            throw ValidationException::withMessages([
                'draw_id' => 'Selected market was not found.',
            ]);
        }

        if (! empty($validated['board']) && $validated['board'] !== $draw['board']) {
            throw ValidationException::withMessages([
                'board' => 'Board does not match the selected market.',
            ]);
        }

        $betType = $validated['bet_type']
            ?? ($draw['board'] === 'matka' ? ($draw['bet_type'] ?? 'jodi') : 'number');

        $typeCfg = collect($draw['bet_types'] ?? [])->firstWhere('id', $betType);
        if (! $typeCfg) {
            throw ValidationException::withMessages([
                'bet_type' => 'Invalid bet type for this market.',
            ]);
        }

        if (! ($typeCfg['open'] ?? false) || ($draw['status'] ?? null) !== 'open') {
            throw ValidationException::withMessages([
                'bet_type' => 'Betting is closed for this type / market.',
            ]);
        }

        $number = (int) $validated['numbers'][0];
        $min = (int) $typeCfg['min'];
        $max = (int) $typeCfg['max'];
        if ($number < $min || $number > $max) {
            throw ValidationException::withMessages([
                'numbers' => "Number must be between {$min} and {$max} for {$typeCfg['label']}.",
            ]);
        }

        $amount = round((float) $validated['amount'], 2);
        $multiplier = (float) $typeCfg['multiplier'];
        $user = $request->user();

        if ((float) $user->wallet_balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance. Please deposit first.',
            ]);
        }

        $bet = DB::transaction(function () use ($user, $draw, $number, $amount, $betType) {
            $lockedUser = $user->newQuery()->whereKey($user->id)->lockForUpdate()->first();

            if ((float) $lockedUser->wallet_balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Insufficient wallet balance. Please deposit first.',
                ]);
            }

            $lockedUser->wallet_balance = (float) $lockedUser->wallet_balance - $amount;
            $lockedUser->save();

            $typeLabel = collect($draw['bet_types'] ?? [])->firstWhere('id', $betType)['label'] ?? $betType;

            return Bet::create([
                'user_id' => $lockedUser->id,
                'draw_id' => $draw['id'],
                'draw_name' => ($draw['name'] ?? $draw['display_name']).' · '.$typeLabel,
                'board' => $draw['board'],
                'market_slug' => $draw['market_slug'] ?? null,
                'bet_type' => $betType,
                'numbers' => [$number],
                'amount' => $amount,
                'status' => 'pending',
                'prize' => 0,
                'draw_at' => $draw['draw_at'],
            ]);
        });

        $user->refresh();
        $digits = (int) ($typeCfg['digits'] ?? 2);

        return response()->json([
            'message' => 'Bet placed successfully.',
            'bet' => [
                'id' => $bet->id,
                'draw_name' => $bet->draw_name,
                'board' => $bet->board,
                'bet_type' => $bet->bet_type,
                'numbers' => $bet->numbers,
                'numbers_display' => collect($bet->numbers)
                    ->map(fn ($n) => str_pad((string) $n, $digits, '0', STR_PAD_LEFT))
                    ->all(),
                'amount' => (float) $bet->amount,
                'potential_win' => round((float) $bet->amount * $multiplier, 2),
                'status' => $bet->status,
                'draw_at' => optional($bet->draw_at)?->toIso8601String(),
                'created_at' => $bet->created_at?->toIso8601String(),
            ],
            'wallet_balance' => (float) $user->wallet_balance,
        ], 201);
    }
}

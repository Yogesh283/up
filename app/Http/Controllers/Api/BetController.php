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
        $minNumber = (int) config('betting.min_number', 0);
        $maxNumber = (int) config('betting.max_number', 99);
        $defaultAmount = (float) config('betting.ticket_price', 10);
        $minAmount = (float) config('betting.min_amount', $defaultAmount);
        $maxAmount = (float) config('betting.max_amount', 10000);
        $pickCount = (int) config('betting.pick_count', 1);

        $validated = $request->validate([
            'draw_id' => 'required|integer',
            'numbers' => 'required|array',
            'numbers.*' => "integer|min:{$minNumber}|max:{$maxNumber}",
            'amount' => "nullable|numeric|min:{$minAmount}|max:{$maxAmount}",
            'board' => 'nullable|in:king,matka',
        ]);

        $payload = $results->toResultsPayload();
        $draw = Draws::find((int) $validated['draw_id'], $payload);

        if (! $draw) {
            throw ValidationException::withMessages([
                'draw_id' => 'Selected market was not found.',
            ]);
        }

        if (($draw['status'] ?? null) !== 'open') {
            throw ValidationException::withMessages([
                'draw_id' => 'Betting is closed for this market (result already declared).',
            ]);
        }

        if (! empty($validated['board']) && $validated['board'] !== $draw['board']) {
            throw ValidationException::withMessages([
                'board' => 'Board does not match the selected market.',
            ]);
        }

        $numbers = collect($validated['numbers'])
            ->map(fn ($n) => (int) $n)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($numbers) !== $pickCount) {
            throw ValidationException::withMessages([
                'numbers' => $pickCount === 1
                    ? 'Please select exactly 1 number (00–99).'
                    : "Please select exactly {$pickCount} numbers.",
            ]);
        }

        foreach ($numbers as $number) {
            if ($number < $minNumber || $number > $maxNumber) {
                throw ValidationException::withMessages([
                    'numbers' => "Numbers must be between {$minNumber} and {$maxNumber}.",
                ]);
            }
        }

        $amount = isset($validated['amount'])
            ? (float) $validated['amount']
            : (float) $draw['ticket_price'];

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
                'draw_name' => $draw['display_name'] ?? $draw['name'],
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
                'board' => $draw['board'],
                'numbers' => $bet->numbers,
                'numbers_display' => collect($bet->numbers)
                    ->map(fn ($n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT))
                    ->all(),
                'amount' => (float) $bet->amount,
                'status' => $bet->status,
                'draw_at' => optional($bet->draw_at)?->toIso8601String(),
                'created_at' => $bet->created_at?->toIso8601String(),
            ],
            'wallet_balance' => (float) $user->wallet_balance,
        ], 201);
    }
}

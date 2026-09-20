<?php

namespace App\Services;

use App\Models\Bet;
use App\Support\Draws;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BetSettlementService
{
    /**
     * Settle pending bets against live declared results.
     * Payout: stake × multiplier (default 1₹ → 9₹).
     *
     * @param  array<string, mixed>|null  $payload
     * @return array{settled:int,won:int,lost:int,refunded:int,credited:float}
     */
    public function settle(?array $payload = null): array
    {
        $payload ??= app(CombinedResultsService::class)->toResultsPayload(settleBets: false);

        return $this->settleFromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{settled:int,won:int,lost:int,refunded:int,credited:float}
     */
    public function settleFromPayload(array $payload): array
    {
        $stats = [
            'settled' => 0,
            'won' => 0,
            'lost' => 0,
            'refunded' => 0,
            'credited' => 0.0,
        ];

        $declared = $this->declaredResultsMap($payload);
        if ($declared === []) {
            return $stats;
        }

        $pending = Bet::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return $stats;
        }

        $multiplier = (float) config('betting.prize_multiplier', 9);

        foreach ($pending as $bet) {
            $drawId = (int) $bet->draw_id;
            if (! isset($declared[$drawId])) {
                continue;
            }

            $info = $declared[$drawId];
            $outcome = $this->settleOne($bet, $info, $multiplier);
            if ($outcome === null) {
                continue;
            }

            $stats['settled']++;
            $stats[$outcome['status']] = ($stats[$outcome['status']] ?? 0) + 1;
            $stats['credited'] += $outcome['credited'];
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{result:?string,holiday:bool,board:string,name:string}>
     */
    protected function declaredResultsMap(array $payload): array
    {
        $map = [];

        foreach ($payload['king_results'] ?? $payload['results'] ?? [] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $id = Draws::marketId('king', $slug);
            $today = strtoupper(trim((string) ($row['today_result'] ?? 'XX')));
            if ($today === '' || $today === 'XX') {
                continue;
            }
            $map[$id] = [
                'board' => 'king',
                'name' => (string) ($row['name'] ?? $slug),
                'result' => $today,
                'holiday' => false,
            ];
        }

        foreach ($payload['matka_results'] ?? [] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $id = Draws::marketId('matka', $slug);
            $full = strtoupper(trim((string) ($row['full_result'] ?? '')));
            if ($full === 'HOLIDAY') {
                $map[$id] = [
                    'board' => 'matka',
                    'name' => (string) ($row['name'] ?? $slug),
                    'result' => null,
                    'holiday' => true,
                ];

                continue;
            }

            $jodi = $row['jodi'] ?? null;
            if ($jodi === null || $jodi === '' || strtoupper((string) $jodi) === 'XX') {
                continue;
            }

            $map[$id] = [
                'board' => 'matka',
                'name' => (string) ($row['name'] ?? $slug),
                'result' => (string) $jodi,
                'holiday' => false,
            ];
        }

        return $map;
    }

    /**
     * @param  array{result:?string,holiday:bool,board:string,name:string}  $info
     * @return array{status:string,credited:float}|null
     */
    protected function settleOne(Bet $bet, array $info, float $multiplier): ?array
    {
        try {
            return DB::transaction(function () use ($bet, $info, $multiplier) {
                /** @var Bet|null $locked */
                $locked = Bet::query()->whereKey($bet->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'pending') {
                    return null;
                }

                $user = $locked->user()->lockForUpdate()->first();
                if (! $user) {
                    return null;
                }

                if (! empty($info['holiday'])) {
                    $refund = (float) $locked->amount;
                    $user->wallet_balance = (float) $user->wallet_balance + $refund;
                    $user->save();

                    $locked->status = 'refunded';
                    $locked->prize = $refund;
                    $locked->result_value = 'HOLIDAY';
                    $locked->settled_at = now();
                    if ($locked->board === null) {
                        $locked->board = $info['board'];
                    }
                    $locked->save();

                    return ['status' => 'refunded', 'credited' => $refund];
                }

                $declared = $this->normalizeNumber((string) ($info['result'] ?? ''));
                if ($declared === null) {
                    return null;
                }

                $picked = collect($locked->numbers ?? [])
                    ->map(fn ($n) => $this->normalizeNumber((string) $n))
                    ->filter(fn ($n) => $n !== null)
                    ->values()
                    ->all();

                $won = in_array($declared, $picked, true);
                $credit = 0.0;

                if ($won) {
                    $credit = round((float) $locked->amount * $multiplier, 2);
                    $user->wallet_balance = (float) $user->wallet_balance + $credit;
                    $user->save();
                    $locked->status = 'won';
                    $locked->prize = $credit;
                } else {
                    $locked->status = 'lost';
                    $locked->prize = 0;
                }

                $locked->result_value = str_pad((string) $declared, 2, '0', STR_PAD_LEFT);
                $locked->settled_at = now();
                if ($locked->board === null) {
                    $locked->board = $info['board'];
                }
                $locked->save();

                return [
                    'status' => $won ? 'won' : 'lost',
                    'credited' => $credit,
                ];
            });
        } catch (\Throwable $e) {
            Log::warning('Bet settlement failed', [
                'bet_id' => $bet->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function normalizeNumber(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || strtoupper($value) === 'XX') {
            return null;
        }

        if (! preg_match('/^\d{1,2}$/', $value)) {
            return null;
        }

        return (int) $value;
    }
}

<?php

namespace App\Services;

use App\Models\Bet;
use App\Support\Draws;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BetSettlementService
{
    /**
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

        $markets = $this->marketsMap($payload);
        if ($markets === []) {
            return $stats;
        }

        $pending = Bet::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        foreach ($pending as $bet) {
            $drawId = (int) $bet->draw_id;
            if (! isset($markets[$drawId])) {
                continue;
            }

            $outcome = $this->settleOne($bet, $markets[$drawId]);
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
     * @return array<int, array<string, mixed>>
     */
    protected function marketsMap(array $payload): array
    {
        $map = [];

        foreach ($payload['king_results'] ?? $payload['results'] ?? [] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $map[Draws::marketId('king', $slug)] = [
                'board' => 'king',
                'row' => $row,
            ];
        }

        foreach ($payload['matka_results'] ?? [] as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $map[Draws::marketId('matka', $slug)] = [
                'board' => 'matka',
                'row' => $row,
            ];
        }

        return $map;
    }

    /**
     * @param  array{board:string,row:array<string,mixed>}  $market
     * @return array{status:string,credited:float}|null
     */
    protected function settleOne(Bet $bet, array $market): ?array
    {
        $resolved = $this->resolveOutcome($bet, $market);
        if ($resolved === null) {
            return null; // still waiting
        }

        try {
            return DB::transaction(function () use ($bet, $resolved, $market) {
                /** @var Bet|null $locked */
                $locked = Bet::query()->whereKey($bet->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== 'pending') {
                    return null;
                }

                $user = $locked->user()->lockForUpdate()->first();
                if (! $user) {
                    return null;
                }

                if ($resolved['holiday'] ?? false) {
                    $refund = (float) $locked->amount;
                    $user->wallet_balance = (float) $user->wallet_balance + $refund;
                    $user->save();

                    $locked->status = 'refunded';
                    $locked->prize = $refund;
                    $locked->result_value = 'HOLIDAY';
                    $locked->settled_at = now();
                    $locked->board ??= $market['board'];
                    $locked->save();

                    return ['status' => 'refunded', 'credited' => $refund];
                }

                $multiplier = $this->multiplierFor($locked);
                $won = (bool) $resolved['won'];
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

                $locked->result_value = (string) ($resolved['result'] ?? '');
                $locked->settled_at = now();
                $locked->board ??= $market['board'];
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

    /**
     * @param  array{board:string,row:array<string,mixed>}  $market
     * @return array{won:bool,result:string,holiday?:bool}|null
     */
    protected function resolveOutcome(Bet $bet, array $market): ?array
    {
        $row = $market['row'];
        $board = $market['board'];
        $picked = (int) (($bet->numbers[0] ?? -1));
        $type = $bet->bet_type ?: ($board === 'matka' ? 'jodi' : 'number');

        if ($board === 'king' || $type === 'number') {
            $today = strtoupper(trim((string) ($row['today_result'] ?? 'XX')));
            if ($today === '' || $today === 'XX') {
                return null;
            }
            $declared = $this->normalizeDigits($today, 2);
            if ($declared === null) {
                return null;
            }

            return [
                'won' => $picked === $declared,
                'result' => str_pad((string) $declared, 2, '0', STR_PAD_LEFT),
            ];
        }

        $full = strtoupper(trim((string) ($row['full_result'] ?? '')));
        if ($full === 'HOLIDAY') {
            return ['won' => false, 'result' => 'HOLIDAY', 'holiday' => true];
        }

        return match ($type) {
            'single_open' => $this->matchInt($row['open_ank'] ?? null, $picked, 1),
            'single_close' => $this->matchInt($row['close_ank'] ?? null, $picked, 1),
            'jodi' => $this->matchJodi($row['jodi'] ?? null, $picked),
            'pana_open' => $this->matchPana($row['open_pana'] ?? null, $picked),
            'pana_close' => $this->matchPana($row['close_pana'] ?? null, $picked),
            default => null,
        };
    }

    /**
     * @return array{won:bool,result:string}|null
     */
    protected function matchInt(mixed $value, int $picked, int $digits): ?array
    {
        if ($value === null || $value === '' || strtoupper((string) $value) === 'XX') {
            return null;
        }
        $declared = $this->normalizeDigits((string) $value, $digits);
        if ($declared === null) {
            return null;
        }

        return [
            'won' => $picked === $declared,
            'result' => str_pad((string) $declared, $digits, '0', STR_PAD_LEFT),
        ];
    }

    /**
     * @return array{won:bool,result:string}|null
     */
    protected function matchJodi(mixed $value, int $picked): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if (! preg_match('/^\d{2}$/', $raw)) {
            return null; // wait until full 2-digit jodi
        }

        return $this->matchInt($raw, $picked, 2);
    }

    /**
     * @return array{won:bool,result:string}|null
     */
    protected function matchPana(mixed $value, int $picked): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string) $value);
        if (! preg_match('/^\d{3}$/', $raw)) {
            return null;
        }

        $declared = (int) $raw;

        return [
            'won' => $picked === $declared,
            'result' => $raw,
        ];
    }

    protected function normalizeDigits(string $value, int $digits): ?int
    {
        $value = trim($value);
        if ($value === '' || strtoupper($value) === 'XX') {
            return null;
        }
        if (! preg_match('/^\d{1,'.$digits.'}$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    protected function multiplierFor(Bet $bet): float
    {
        if ($bet->board === 'matka' || str_starts_with((string) $bet->bet_type, 'single') || in_array($bet->bet_type, ['jodi', 'pana_open', 'pana_close'], true)) {
            $cfg = Draws::matkaType((string) $bet->bet_type);

            return (float) ($cfg['multiplier'] ?? config('betting.prize_multiplier', 9));
        }

        return (float) config('betting.king.multiplier', config('betting.prize_multiplier', 9));
    }
}

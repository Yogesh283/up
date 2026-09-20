<?php

namespace App\Support;

use App\Services\CombinedResultsService;
use Carbon\Carbon;

class Draws
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function open(?array $payload = null): array
    {
        $payload ??= app(CombinedResultsService::class)->toResultsPayload(settleBets: false);

        return self::fromPayload($payload, onlyOpen: true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(?array $payload = null): array
    {
        $payload ??= app(CombinedResultsService::class)->toResultsPayload(settleBets: false);

        return self::fromPayload($payload, onlyOpen: false);
    }

    public static function find(int $id, ?array $payload = null): ?array
    {
        foreach (self::all($payload) as $draw) {
            if ((int) $draw['id'] === $id) {
                return $draw;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fromPayload(array $payload, bool $onlyOpen = true): array
    {
        $draws = [];

        foreach ($payload['king_results'] ?? $payload['results'] ?? [] as $row) {
            $draw = self::mapKingMarket($row);
            if ($draw && (! $onlyOpen || $draw['status'] === 'open')) {
                $draws[] = $draw;
            }
        }

        foreach ($payload['matka_results'] ?? [] as $row) {
            $draw = self::mapMatkaMarket($row);
            if ($draw && (! $onlyOpen || $draw['status'] === 'open')) {
                $draws[] = $draw;
            }
        }

        return $draws;
    }

    public static function marketId(string $board, string $slug): int
    {
        return (int) sprintf('%u', crc32(strtolower($board).':'.strtolower($slug)));
    }

    public static function matkaType(string $type): ?array
    {
        $types = config('betting.matka.types', []);

        return $types[$type] ?? null;
    }

    /**
     * Betting allowed only until N minutes before result time.
     */
    public static function isWithinBettingWindow(?string $drawAtIso): bool
    {
        if (! $drawAtIso) {
            return true;
        }

        try {
            $drawAt = Carbon::parse($drawAtIso, 'Asia/Kolkata');
        } catch (\Throwable) {
            return true;
        }

        $minutes = max(0, (int) config('betting.close_minutes_before', 40));
        $cutoff = $drawAt->copy()->subMinutes($minutes);

        return Carbon::now('Asia/Kolkata')->lt($cutoff);
    }

    public static function bettingClosesAt(?string $drawAtIso): ?string
    {
        if (! $drawAtIso) {
            return null;
        }

        try {
            $drawAt = Carbon::parse($drawAtIso, 'Asia/Kolkata');
        } catch (\Throwable) {
            return null;
        }

        $minutes = max(0, (int) config('betting.close_minutes_before', 40));

        return $drawAt->copy()->subMinutes($minutes)->toIso8601String();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected static function mapKingMarket(array $row): ?array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $slug = (string) ($row['slug'] ?? strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-')));
        $cfg = config('betting.king', []);
        $multiplier = (float) ($cfg['multiplier'] ?? 90);
        $timeLabel = (string) ($row['close_time'] ?? $row['open_time'] ?? '—');
        $awaiting = MarketSorter::isAwaitingResult($row, 'king');
        $drawAt = MarketSorter::parseDrawAt($row)?->toIso8601String()
            ?? self::estimateDrawAt($timeLabel);
        $windowOpen = self::isWithinBettingWindow($drawAt);
        $open = $awaiting && $windowOpen;
        $closesAt = self::bettingClosesAt($drawAt);

        return [
            'id' => self::marketId('king', $slug ?: 'market'),
            'board' => 'king',
            'board_label' => 'Satta King',
            'market_slug' => $slug ?: 'market',
            'name' => $name,
            'display_name' => $name.' · Satta King',
            'draw_at' => $drawAt,
            'betting_closes_at' => $closesAt,
            'time_label' => $timeLabel,
            'prize' => '1₹ = ₹'.number_format($multiplier, 0),
            'ticket_price' => 1,
            'ticket_price_display' => '₹1+',
            'pick_count' => 1,
            'min_number' => (int) ($cfg['min_number'] ?? 0),
            'max_number' => (int) ($cfg['max_number'] ?? 99),
            'multiplier' => $multiplier,
            'bet_type' => 'number',
            'bet_types' => [
                [
                    'id' => 'number',
                    'label' => 'Number',
                    'hint' => '00–99',
                    'multiplier' => $multiplier,
                    'digits' => 2,
                    'min' => 0,
                    'max' => 99,
                    'open' => $open,
                ],
            ],
            'status' => $open ? 'open' : 'closed',
            'close_reason' => $open
                ? null
                : (! $awaiting ? 'result_declared' : 'cutoff'),
            'current_result' => (string) ($row['today_result'] ?? 'XX'),
            'urgency_bucket' => $row['urgency_bucket'] ?? null,
            'is_due' => (bool) ($row['is_due'] ?? false),
            'is_next_up' => (bool) ($row['is_next_up'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected static function mapMatkaMarket(array $row): ?array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $slug = (string) ($row['slug'] ?? strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-')));
        $timeLabel = (string) ($row['close_time'] ?? $row['open_time'] ?? '—');
        $drawAt = MarketSorter::parseDrawAt($row)?->toIso8601String()
            ?? self::estimateDrawAt($timeLabel);
        $windowOpen = self::isWithinBettingWindow($drawAt);
        $closesAt = self::bettingClosesAt($drawAt);
        $availability = $row['betting'] ?? [];

        $betTypes = [];
        foreach (config('betting.matka.types', []) as $id => $cfg) {
            $isOpen = (bool) ($availability[$id] ?? false) && $windowOpen;
            $betTypes[] = [
                'id' => $id,
                'label' => $cfg['label'],
                'hint' => $cfg['hint'],
                'multiplier' => (float) $cfg['multiplier'],
                'digits' => (int) $cfg['digits'],
                'min' => (int) $cfg['min'],
                'max' => (int) $cfg['max'],
                'session' => $cfg['session'] ?? null,
                'open' => $isOpen,
            ];
        }

        $anyOpen = collect($betTypes)->contains(fn ($t) => $t['open']);
        $default = collect($betTypes)->firstWhere('open', true) ?? ($betTypes[0] ?? null);
        $resultStillPending = MarketSorter::isAwaitingResult($row, 'matka');

        return [
            'id' => self::marketId('matka', $slug ?: 'market'),
            'board' => 'matka',
            'board_label' => 'Kalyan Matka',
            'market_slug' => $slug ?: 'market',
            'name' => $name,
            'display_name' => $name.' · Kalyan Matka',
            'draw_at' => $drawAt,
            'betting_closes_at' => $closesAt,
            'time_label' => $timeLabel,
            'prize' => 'Single 9× · Jodi 90× · Patti 900×',
            'ticket_price' => 1,
            'ticket_price_display' => '₹1+',
            'pick_count' => 1,
            'min_number' => 0,
            'max_number' => 999,
            'multiplier' => (float) ($default['multiplier'] ?? 9),
            'bet_type' => $default['id'] ?? 'jodi',
            'bet_types' => $betTypes,
            'status' => $anyOpen ? 'open' : 'closed',
            'close_reason' => $anyOpen
                ? null
                : (! $resultStillPending ? 'result_declared' : (! $windowOpen ? 'cutoff' : 'session_closed')),
            'current_result' => (string) ($row['full_result'] ?? $row['jodi'] ?? 'XX'),
            'urgency_bucket' => $row['urgency_bucket'] ?? null,
            'is_due' => (bool) ($row['is_due'] ?? false),
            'is_next_up' => (bool) ($row['is_next_up'] ?? false),
            'open_pana' => $row['open_pana'] ?? null,
            'close_pana' => $row['close_pana'] ?? null,
            'jodi' => $row['jodi'] ?? null,
            'open_ank' => $row['open_ank'] ?? null,
            'close_ank' => $row['close_ank'] ?? null,
        ];
    }

    protected static function estimateDrawAt(string $timeLabel): string
    {
        $tz = 'Asia/Kolkata';
        $today = Carbon::now($tz)->startOfDay();

        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $timeLabel, $m)) {
            try {
                return Carbon::parse(
                    $today->toDateString().' '.$m[1].':'.$m[2].' '.strtoupper($m[3]),
                    $tz,
                )->toIso8601String();
            } catch (\Throwable) {
                //
            }
        }

        return $today->copy()->setTime(23, 59)->toIso8601String();
    }
}

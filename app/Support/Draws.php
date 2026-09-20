<?php

namespace App\Support;

use App\Services\CombinedResultsService;
use Carbon\Carbon;

class Draws
{
    /**
     * Open markets from live Satta King + Kalyan Matka boards.
     *
     * @return list<array<string, mixed>>
     */
    public static function open(?array $payload = null): array
    {
        $payload ??= app(CombinedResultsService::class)->toResultsPayload();

        return self::fromPayload($payload, onlyOpen: true);
    }

    /**
     * All markets (open + closed) for dashboard listing.
     *
     * @return list<array<string, mixed>>
     */
    public static function all(?array $payload = null): array
    {
        $payload ??= app(CombinedResultsService::class)->toResultsPayload();

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
        $ticket = (float) config('betting.ticket_price', 1);
        $multiplier = (float) config('betting.prize_multiplier', 9);
        $pickCount = (int) config('betting.pick_count', 1);
        $minNumber = (int) config('betting.min_number', 0);
        $maxNumber = (int) config('betting.max_number', 99);
        $prizeDisplay = '1₹ = ₹'.number_format($multiplier, 0);

        $draws = [];

        foreach ($payload['king_results'] ?? $payload['results'] ?? [] as $row) {
            $draw = self::mapMarket($row, 'king', $ticket, $prizeDisplay, $pickCount, $minNumber, $maxNumber);
            if ($draw && (! $onlyOpen || $draw['status'] === 'open')) {
                $draws[] = $draw;
            }
        }

        foreach ($payload['matka_results'] ?? [] as $row) {
            $draw = self::mapMarket($row, 'matka', $ticket, $prizeDisplay, $pickCount, $minNumber, $maxNumber);
            if ($draw && (! $onlyOpen || $draw['status'] === 'open')) {
                $draws[] = $draw;
            }
        }

        return $draws;
    }

    /**
     * Stable unsigned id from board + slug.
     */
    public static function marketId(string $board, string $slug): int
    {
        return (int) sprintf('%u', crc32(strtolower($board).':'.strtolower($slug)));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected static function mapMarket(
        array $row,
        string $board,
        float $ticket,
        string $prizeDisplay,
        int $pickCount,
        int $minNumber,
        int $maxNumber,
    ): ?array {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $slug = (string) ($row['slug'] ?? strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-')));
        if ($slug === '') {
            $slug = 'market';
        }

        $timeLabel = (string) ($row['close_time'] ?? $row['open_time'] ?? '—');
        $open = self::isMarketOpen($row, $board);
        $drawAt = self::estimateDrawAt($timeLabel);

        $boardLabel = $board === 'matka' ? 'Kalyan Matka' : 'Satta King';
        $current = $board === 'matka'
            ? (string) ($row['jodi'] ?? $row['full_result'] ?? $row['today_result'] ?? 'XX')
            : (string) ($row['today_result'] ?? 'XX');

        return [
            'id' => self::marketId($board, $slug),
            'board' => $board,
            'board_label' => $boardLabel,
            'market_slug' => $slug,
            'name' => $name,
            'display_name' => $name.' · '.$boardLabel,
            'draw_at' => $drawAt,
            'time_label' => $timeLabel,
            'prize' => $prizeDisplay,
            'ticket_price' => $ticket,
            'ticket_price_display' => '₹'.number_format($ticket, 0),
            'pick_count' => $pickCount,
            'min_number' => $minNumber,
            'max_number' => $maxNumber,
            'status' => $open ? 'open' : 'closed',
            'current_result' => $current !== '' ? $current : 'XX',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function isMarketOpen(array $row, string $board): bool
    {
        if ($board === 'matka') {
            $full = strtoupper(trim((string) ($row['full_result'] ?? '')));
            if ($full === 'HOLIDAY') {
                return false;
            }

            $jodi = $row['jodi'] ?? null;
            if ($jodi !== null && $jodi !== '' && strtoupper((string) $jodi) !== 'XX') {
                // Jodi declared — market closed for jodi bets.
                return false;
            }

            if ($full !== '' && $full !== 'XX' && preg_match('/^\d{3}-\d{1,2}-\d{3}$/', $full)) {
                return false;
            }

            return true;
        }

        $today = strtoupper(trim((string) ($row['today_result'] ?? 'XX')));

        return $today === '' || $today === 'XX';
    }

    protected static function estimateDrawAt(string $timeLabel): string
    {
        $tz = 'Asia/Kolkata';
        $today = Carbon::now($tz)->startOfDay();

        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $timeLabel, $m)) {
            try {
                $parsed = Carbon::parse(
                    $today->toDateString().' '.$m[1].':'.$m[2].' '.strtoupper($m[3]),
                    $tz,
                );

                return $parsed->toIso8601String();
            } catch (\Throwable) {
                // fall through
            }
        }

        return $today->copy()->setTime(23, 59)->toIso8601String();
    }
}

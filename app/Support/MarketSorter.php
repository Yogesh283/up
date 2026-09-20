<?php

namespace App\Support;

use Carbon\Carbon;

class MarketSorter
{
    /**
     * Pending markets whose time has passed or is next → top.
     * Declared markets go to the bottom.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function byUrgency(array $rows, string $board = 'king'): array
    {
        $now = Carbon::now('Asia/Kolkata');

        return collect($rows)
            ->map(function (array $row) use ($now, $board) {
                $pending = self::isAwaitingResult($row, $board);
                $drawAt = self::parseDrawAt($row);
                $ts = $drawAt?->timestamp ?? PHP_INT_MAX;

                if (! $pending) {
                    // Declared / holiday — bottom, newest declared first.
                    $bucket = 2;
                    $sort = -$ts;
                } elseif ($drawAt && $drawAt->lte($now)) {
                    // Time ho chuka, result pending — top (most recently due first).
                    $bucket = 0;
                    $sort = -$ts;
                } else {
                    // Abhi aane wala — soonest first.
                    $bucket = 1;
                    $sort = $ts;
                }

                $row['urgency_bucket'] = $bucket;
                $row['draw_at_iso'] = $drawAt?->toIso8601String();
                $row['is_due'] = $bucket === 0;
                $row['is_next_up'] = $bucket === 1;

                return [
                    'bucket' => $bucket,
                    'sort' => $sort,
                    'row' => $row,
                ];
            })
            ->sortBy([
                ['bucket', 'asc'],
                ['sort', 'asc'],
            ])
            ->map(fn (array $item) => $item['row'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function isAwaitingResult(array $row, string $board): bool
    {
        if ($board === 'matka') {
            $full = strtoupper(trim((string) ($row['full_result'] ?? '')));
            if ($full === 'HOLIDAY') {
                return false;
            }

            $close = $row['close_pana'] ?? $row['cases']['close']['value'] ?? null;
            if ($close !== null && $close !== '' && strtoupper((string) $close) !== 'XX') {
                return false;
            }

            // Still waiting for open/jodi/close pieces.
            return true;
        }

        $today = strtoupper(trim((string) ($row['today_result'] ?? 'XX')));

        return $today === '' || $today === 'XX';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function parseDrawAt(array $row): ?Carbon
    {
        $tz = 'Asia/Kolkata';
        $label = (string) ($row['close_time'] ?? $row['open_time'] ?? $row['time_label'] ?? '');

        if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)/i', $label, $m)) {
            try {
                return Carbon::parse(
                    now($tz)->toDateString().' '.$m[1].':'.$m[2].' '.strtoupper($m[3]),
                    $tz,
                );
            } catch (\Throwable) {
                // fall through
            }
        }

        if (! empty($row['draw_at_iso'])) {
            try {
                return Carbon::parse($row['draw_at_iso'], $tz);
            } catch (\Throwable) {
                //
            }
        }

        if (! empty($row['draw_at'])) {
            try {
                return Carbon::parse($row['draw_at'], $tz);
            } catch (\Throwable) {
                //
            }
        }

        return null;
    }

    /**
     * Ank from 3-digit pana (sum of digits % 10).
     */
    public static function ankFromPana(?string $pana): ?int
    {
        $pana = trim((string) $pana);
        if (! preg_match('/^\d{3}$/', $pana)) {
            return null;
        }

        return array_sum(array_map('intval', str_split($pana))) % 10;
    }
}

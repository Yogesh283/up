<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SattaKalyanMatkaService
{
    /**
     * @return array<string, mixed>
     */
    public function toResultsPayload(): array
    {
        $board = $this->fetchBoard();
        $today = $board['today_date'] ?? now('Asia/Kolkata')->toDateString();
        $yesterday = $board['yesterday_date'] ?? Carbon::parse($today, 'Asia/Kolkata')->subDay()->toDateString();

        $mapped = collect($board['rows'])
            ->map(fn (array $row) => $this->mapRow($row, $today, $yesterday))
            ->values();

        $declared = $mapped->filter(fn (array $r) => ($r['full_result'] ?? 'XX') !== 'XX' && ($r['full_result'] ?? '') !== 'HOLIDAY')->values();

        $latest = $declared->first() ?? $mapped->first();
        if ($latest) {
            $latest['display_value'] = $latest['full_result'] ?? $latest['today_result'] ?? 'XX';
        }

        return [
            'latest' => $latest,
            'results' => $mapped->all(),
            'matka_results' => $mapped->all(),
            'declared' => $declared->all(),
            'date' => $today,
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'today_label' => 'Result',
            'yesterday_label' => 'Open-Jodi-Close',
            'banner_text' => 'Live Kalyan Matka Result · '.Carbon::parse($today)->format('F j, Y'),
            'source' => 'sattakalyanmatka.net',
            'counts' => [
                'total' => $mapped->count(),
                'declared' => $declared->count(),
                'pending' => $mapped->count() - $declared->count(),
            ],
            'error' => $board['error'],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, today_date: string, yesterday_date: string, error: ?string}
     */
    public function fetchBoard(): array
    {
        return Cache::remember('satta_kalyan_matka.board.live', 15, function () {
            $parsed = $this->fetchAndParse();

            if (($parsed['rows'] ?? []) !== []) {
                Cache::put('satta_kalyan_matka.board.last_ok', $parsed, now()->addHours(6));

                return $parsed;
            }

            $cached = Cache::get('satta_kalyan_matka.board.last_ok');
            if (is_array($cached) && ($cached['rows'] ?? []) !== []) {
                $cached['error'] = ($parsed['error'] ?? 'Fetch failed').' · showing cached matka board';

                return $cached;
            }

            return $parsed;
        });
    }

    /**
     * @return array{rows: list<array<string, mixed>>, today_date: string, yesterday_date: string, error: ?string}
     */
    protected function fetchAndParse(): array
    {
        $base = rtrim((string) config('services.satta_kalyan_matka.base_url', 'https://sattakalyanmatka.net'), '/');
        $empty = [
            'rows' => [],
            'today_date' => now('Asia/Kolkata')->toDateString(),
            'yesterday_date' => now('Asia/Kolkata')->subDay()->toDateString(),
            'error' => null,
        ];

        $direct = $this->httpGet($base.'/', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
        ]);

        $htmlRows = [];
        if ($direct['ok'] && ! $this->isCloudflareChallenge($direct['body'])) {
            $htmlRows = $this->parseHtml($direct['body']);
        }

        $proxyBase = rtrim((string) config('services.satta_kalyan_matka.proxy_url', config('services.satta_king_fast.proxy_url', 'https://r.jina.ai')), '/');
        $proxy = $this->httpGet($proxyBase.'/https://sattakalyanmatka.net/', [
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/plain,text/markdown,*/*',
        ], 40);

        $mdRows = $proxy['ok'] ? $this->parseMarkdown($proxy['body']) : [];

        // Prefer the larger market list.
        $rows = count($mdRows) >= count($htmlRows) ? $mdRows : $htmlRows;
        if ($rows === [] && $htmlRows !== []) {
            $rows = $htmlRows;
        }

        if ($rows === []) {
            $empty['error'] = $this->isCloudflareChallenge($direct['body'] ?? '')
                ? 'Cloudflare blocked sattakalyanmatka.net; proxy parse failed'
                : 'Unable to load sattakalyanmatka.net';

            return $empty;
        }

        return [
            'rows' => $rows,
            'today_date' => now('Asia/Kolkata')->toDateString(),
            'yesterday_date' => now('Asia/Kolkata')->subDay()->toDateString(),
            'error' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseHtml(string $html): array
    {
        $rows = [];
        $seen = [];

        if (preg_match_all(
            '/color:\s*blue[^>]*>\s*([^<]+)<\/span>\s*<span[^>]*color:\s*black[^>]*>\s*([^<]+)<\/span>/iu',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $name = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
                $result = trim($match[2]);
                $key = strtoupper($name);
                if ($name === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $rows[] = [
                    'name' => $name,
                    'result' => $result,
                    'time' => null,
                    'chart_url' => 'https://sattakalyanmatka.net/',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseMarkdown(string $markdown): array
    {
        $rows = [];
        $seen = [];

        // NAME 123-45-678  OR  NAME 123-4  OR  NAME HOLIDAY
        if (preg_match_all(
            '/(?:^|\n)\s*(?:\{)?([A-Z][A-Z0-9 .\/&-]{1,40}?)(?:\})?\s+(\d{3}(?:-\d{1,2}(?:-\d{3})?)?|HOLIDAY)\s*(?:\n+\(?([^\n]{0,40})\)?)?/u',
            $markdown,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $name = trim($match[1], " \t{}");
                $result = trim($match[2]);
                $time = isset($match[3]) ? trim($match[3], " \t()-") : null;

                $upper = strtoupper($name);
                if (
                    $name === ''
                    || isset($seen[$upper])
                    || str_contains($upper, 'SATTA')
                    || str_contains($upper, 'LIVE')
                    || str_contains($upper, 'RESULT')
                    || str_contains($upper, 'CHART')
                    || str_contains($upper, 'FORUM')
                    || strlen($name) < 3
                ) {
                    continue;
                }

                // Skip time-only false positives
                if (preg_match('/^\d/', $name)) {
                    continue;
                }

                $seen[$upper] = true;
                $rows[] = [
                    'name' => $name,
                    'result' => $result,
                    'time' => $time ?: null,
                    'chart_url' => 'https://sattakalyanmatka.net/',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, string $today, string $yesterday): array
    {
        $name = (string) $row['name'];
        $raw = strtoupper(trim((string) ($row['result'] ?? '')));
        $isHoliday = $raw === 'HOLIDAY';
        $parts = ($isHoliday || $raw === '' || $raw === 'XX')
            ? []
            : (preg_split('/-/', $raw) ?: []);
        $open = isset($parts[0]) && preg_match('/^\d{3}$/', $parts[0]) ? $parts[0] : null;
        $jodi = $parts[1] ?? null;
        $close = isset($parts[2]) && preg_match('/^\d{3}$/', $parts[2]) ? $parts[2] : null;
        // Mid formats: 123-4  or  123-45
        if ($open === null && isset($parts[0]) && $parts[0] !== '' && $parts[0] !== 'XX') {
            // keep non-standard open text only if 3 digit failed — leave null for betting
            if (preg_match('/^\d{3}$/', (string) $parts[0])) {
                $open = $parts[0];
            }
        }
        $full = $isHoliday ? 'XX' : ($raw === '' ? 'XX' : $raw);
        $time = $row['time'] ?? '—';
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));
        $openAnk = \App\Support\MarketSorter::ankFromPana(is_string($open) && preg_match('/^\d{3}$/', $open) ? $open : null);
        // Mid result like 123-4 (open pana + open ank only)
        if ($openAnk === null && is_string($jodi) && preg_match('/^\d$/', $jodi) && $close === null) {
            $openAnk = (int) $jodi;
        }
        $closeAnk = \App\Support\MarketSorter::ankFromPana(is_string($close) && preg_match('/^\d{3}$/', $close) ? $close : null);
        if ($closeAnk === null && is_string($jodi) && preg_match('/^\d{2}$/', $jodi)) {
            $closeAnk = (int) substr($jodi, -1);
            if ($openAnk === null) {
                $openAnk = (int) substr($jodi, 0, 1);
            }
        }

        return [
            'id' => 'matka-'.$slug,
            'slug' => $slug,
            'name' => $name,
            'source_type' => 'matka',
            'date' => $today,
            'drawn_at' => $full !== 'XX' ? $today.'T12:00:00+05:30' : null,
            'is_india' => true,
            'is_featured' => in_array(strtoupper($name), [
                'KALYAN', 'KALYAN MORNING', 'KALYAN NIGHT', 'MILAN DAY', 'MILAN NIGHT',
                'RAJDHANI DAY', 'RAJDHANI NIGHT', 'MAIN BAZAR', 'TIME BAZAR',
            ], true),
            'is_regional' => false,
            'has_result' => $full !== 'XX',
            'open_pana' => $open,
            'close_pana' => $close,
            'jodi' => $jodi,
            'open_ank' => $openAnk,
            'close_ank' => $closeAnk,
            'result_string' => $full !== 'XX' ? $full : null,
            'full_result' => $full,
            'cases' => [
                'open' => ['label' => 'Open', 'value' => $open, 'ank' => $openAnk, 'display' => $open ?: '***'],
                'jodi' => ['label' => 'Jodi', 'value' => $jodi, 'ank' => null, 'display' => $jodi ?: '***'],
                'close' => ['label' => 'Close', 'value' => $close, 'ank' => $closeAnk, 'display' => $close ?: '***'],
            ],
            'status' => $isHoliday ? 'holiday' : ($close !== null ? 'closed' : ($open !== null ? 'open_declared' : 'pending')),
            'status_label' => $isHoliday ? 'Holiday' : ($close !== null ? 'Declared' : ($open !== null ? 'Open out' : 'Awaiting')),
            'open_time' => $time,
            'close_time' => $time,
            'is_complete' => $close !== null,
            'chart_url' => $row['chart_url'] ?? 'https://sattakalyanmatka.net/',
            'highlight' => false,
            'numbers' => array_values(array_filter([$open, $jodi, $close])),
            'last_result' => $full,
            'today_result' => $full,
            'last_result_date' => $today,
            'betting' => [
                'single_open' => $openAnk === null && ! $isHoliday,
                'pana_open' => $open === null && ! $isHoliday,
                'jodi' => ($jodi === null || $jodi === '' || ! preg_match('/^\d{2}$/', (string) $jodi)) && ! $isHoliday && $close === null,
                'single_close' => $closeAnk === null && ! $isHoliday && $open !== null,
                'pana_close' => $close === null && ! $isHoliday && $open !== null,
            ],
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{ok:bool,status:int,body:string}
     */
    protected function httpGet(string $url, array $headers = [], int $timeout = 20): array
    {
        try {
            $response = Http::timeout($timeout)->withHeaders($headers)->get($url);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('SattaKalyanMatka HTTP error', ['url' => $url, 'message' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'body' => $e->getMessage()];
        }
    }

    protected function isCloudflareChallenge(string $body): bool
    {
        return str_contains($body, 'Just a moment...')
            || str_contains($body, 'cf-browser-verification')
            || str_contains($body, 'Attention Required! | Cloudflare');
    }
}

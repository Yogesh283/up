<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SattaKingFastService
{
    /**
     * Markets pinned at the top of Results (when present on the board).
     *
     * @var list<string>
     */
    protected array $featuredNames = [
        'DESAWAR',
        'DELHI BAZAR',
        'SHRI GANESH',
        'FARIDABAD',
        'GHAZIABAD',
        'GALI',
        'NOIDA KING',
    ];

    /**
     * Full Results payload from satta-king-fast.com (all markets).
     *
     * @return array<string, mixed>
     */
    public function toResultsPayload(): array
    {
        $board = $this->fetchBoard();
        $rows = $board['rows'];
        $today = $board['today_date'] ?? now('Asia/Kolkata')->toDateString();
        $yesterday = $board['yesterday_date'] ?? Carbon::parse($today, 'Asia/Kolkata')->subDay()->toDateString();

        $mapped = collect($rows)->map(fn (array $row) => $this->mapRow($row, $today, $yesterday))->values();

        $featured = collect();
        $rest = $mapped;

        foreach ($this->featuredNames as $index => $name) {
            $match = $rest->first(
                fn (array $row) => strcasecmp((string) $row['name'], $name) === 0
            );

            if (! $match) {
                continue;
            }

            $featured->push(array_merge($match, [
                'is_featured' => true,
                'is_india' => true,
                'is_regional' => true,
                'featured_order' => $index,
            ]));

            $rest = $rest->reject(fn (array $row) => $row['id'] === $match['id'])->values();
        }

        $rest = $rest->map(fn (array $row) => array_merge($row, [
            'is_featured' => false,
            'featured_order' => 9999,
        ]));

        $sorted = $featured->concat($rest)->values();

        $declaredToday = $sorted->filter(fn (array $r) => ($r['today_result'] ?? 'XX') !== 'XX')->values();
        $declaredLast = $sorted->filter(fn (array $r) => ($r['last_result'] ?? 'XX') !== 'XX')->values();

        $latest = $sorted
            ->filter(fn (array $r) => ($r['today_result'] ?? 'XX') !== 'XX' || ($r['last_result'] ?? 'XX') !== 'XX')
            ->sortByDesc(function (array $r) {
                // Prefer markets with today's result, then featured order.
                $score = (($r['today_result'] ?? 'XX') !== 'XX' ? 1000 : 0)
                    + (($r['is_featured'] ?? false) ? 100 : 0)
                    - (int) ($r['featured_order'] ?? 9999);

                return $score;
            })
            ->first();

        if ($latest) {
            $latest = array_merge($latest, [
                'display_value' => (($latest['today_result'] ?? 'XX') !== 'XX')
                    ? $latest['today_result']
                    : $latest['last_result'],
            ]);
        }

        return [
            'latest' => $latest,
            'results' => $sorted->all(),
            'india_results' => $sorted->where('is_india', true)->values()->all(),
            'other_results' => $sorted->where('is_featured', false)->values()->all(),
            'featured_results' => $featured->all(),
            'declared' => $declaredToday->all(),
            'date' => $today,
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'today_label' => Carbon::parse($today, 'Asia/Kolkata')->format('D. jS'),
            'yesterday_label' => Carbon::parse($yesterday, 'Asia/Kolkata')->format('D. jS'),
            'yesterday_date_label' => Carbon::parse($yesterday, 'Asia/Kolkata')->format('D. jS'),
            'banner_text' => sprintf(
                'Fast Results of %s & %s',
                Carbon::parse($today)->format('F j, Y'),
                Carbon::parse($yesterday)->format('F j, Y'),
            ),
            'note' => null,
            'source' => 'satta-king-fast.com',
            'counts' => [
                'total' => $sorted->count(),
                'featured' => $featured->count(),
                'india' => $featured->count(),
                'other' => $rest->count(),
                'declared' => $declaredToday->count(),
                'declared_last' => $declaredLast->count(),
                'full' => $declaredToday->count(),
                'open' => $declaredToday->count(),
                'pending' => $sorted->filter(fn ($r) => ($r['today_result'] ?? 'XX') === 'XX')->count(),
                'holiday' => 0,
            ],
            'error' => $board['error'],
        ];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, today_date: ?string, yesterday_date: ?string, error: ?string}
     */
    public function fetchBoard(): array
    {
        $cacheKey = 'satta_king_fast.board.'.now('Asia/Kolkata')->format('Y-m-d-H-i');

        return Cache::remember($cacheKey, 45, function () {
            $base = rtrim((string) config('services.satta_king_fast.base_url', 'https://satta-king-fast.com'), '/');

            try {
                $response = Http::timeout(20)
                    ->retry(2, 300)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->get($base.'/');

                if (! $response->successful()) {
                    Log::warning('SattaKingFast fetch failed', ['status' => $response->status()]);

                    return [
                        'rows' => [],
                        'today_date' => now('Asia/Kolkata')->toDateString(),
                        'yesterday_date' => now('Asia/Kolkata')->subDay()->toDateString(),
                        'error' => 'Satta King Fast HTTP '.$response->status(),
                    ];
                }

                return $this->parseHtml($response->body());
            } catch (\Throwable $e) {
                Log::warning('SattaKingFast exception', ['message' => $e->getMessage()]);

                return [
                    'rows' => [],
                    'today_date' => now('Asia/Kolkata')->toDateString(),
                    'yesterday_date' => now('Asia/Kolkata')->subDay()->toDateString(),
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    /**
     * @return array{rows: list<array<string, mixed>>, today_date: ?string, yesterday_date: ?string, error: ?string}
     */
    protected function parseHtml(string $html): array
    {
        $today = now('Asia/Kolkata')->toDateString();
        $yesterday = now('Asia/Kolkata')->subDay()->toDateString();

        if (preg_match('/Satta King Result of (\d{1,2})\w{0,2} (\w+) (\d{4})/i', $html, $m)) {
            try {
                $parsed = Carbon::parse($m[1].' '.$m[2].' '.$m[3], 'Asia/Kolkata');
                $today = $parsed->toDateString();
                $yesterday = $parsed->copy()->subDay()->toDateString();
            } catch (\Throwable) {
                // keep defaults
            }
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query("//tr[contains(@class,'game-result')]");
        $rows = [];

        if ($nodes !== false) {
            foreach ($nodes as $tr) {
                if (! $tr instanceof \DOMElement) {
                    continue;
                }

                $id = trim($tr->getAttribute('id') ?: '');
                $nameNode = $xpath->query(".//h3[contains(@class,'game-name')]", $tr);
                $timeNode = $xpath->query(".//h3[contains(@class,'game-time')]", $tr);
                $linkNode = $xpath->query(".//h3[contains(@class,'game-link')]//a", $tr);
                $yNode = $xpath->query(".//td[contains(@class,'yesterday-number')]//h3", $tr);
                $tNode = $xpath->query(".//td[contains(@class,'today-number')]//h3", $tr);

                $name = ($nameNode && $nameNode->length > 0)
                    ? trim($nameNode->item(0)->textContent)
                    : '';

                if ($name === '' || str_contains(mb_strtoupper($name), 'SHOW YOUR GAME')) {
                    continue;
                }

                $timeRaw = ($timeNode && $timeNode->length > 0)
                    ? trim($timeNode->item(0)->textContent)
                    : '';
                $time = trim(preg_replace('/^at\s+/i', '', $timeRaw) ?? $timeRaw);

                $chart = ($linkNode && $linkNode->length > 0)
                    ? trim($linkNode->item(0)->getAttribute('href'))
                    : 'https://satta-king-fast.com/';

                $yesterdayVal = ($yNode && $yNode->length > 0)
                    ? trim($yNode->item(0)->textContent)
                    : 'XX';
                $todayVal = ($tNode && $tNode->length > 0)
                    ? trim($tNode->item(0)->textContent)
                    : 'XX';

                $yesterdayVal = $this->normalizeResult($yesterdayVal);
                $todayVal = $this->normalizeResult($todayVal);

                $highlight = str_contains($tr->getAttribute('class'), 'highlight');

                $rows[] = [
                    'code' => $id !== '' ? $id : strtoupper(substr(preg_replace('/\W+/', '', $name) ?: 'X', 0, 4)),
                    'name' => $name,
                    'time' => $time !== '' ? $time : '—',
                    'chart_url' => $chart !== '' ? $chart : 'https://satta-king-fast.com/',
                    'yesterday' => $yesterdayVal,
                    'today' => $todayVal,
                    'highlight' => $highlight,
                ];
            }
        }

        return [
            'rows' => $rows,
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'error' => $rows === [] ? 'No markets parsed from satta-king-fast.com' : null,
        ];
    }

    protected function normalizeResult(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || strcasecmp($value, 'XX') === 0 || strcasecmp($value, 'not-announced') === 0 || strcasecmp($value, 'not found') === 0) {
            return 'XX';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, string $today, string $yesterday): array
    {
        $name = (string) $row['name'];
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));
        $last = (string) ($row['yesterday'] ?? 'XX');
        $todayResult = (string) ($row['today'] ?? 'XX');
        $time = (string) ($row['time'] ?? '—');
        $isFeaturedHint = in_array(strtoupper($name), $this->featuredNames, true)
            || collect($this->featuredNames)->contains(fn ($n) => str_contains(strtoupper($name), $n));

        return [
            'id' => ($row['code'] ?? $slug).'-'.$slug,
            'slug' => $slug,
            'code' => $row['code'] ?? null,
            'name' => $name,
            'date' => $today,
            'drawn_at' => $todayResult !== 'XX' ? $today.'T12:00:00+05:30' : ($last !== 'XX' ? $yesterday.'T12:00:00+05:30' : null),
            'display_order' => 9999,
            'is_india' => $isFeaturedHint,
            'is_featured' => false,
            'is_regional' => true,
            'has_result' => $last !== 'XX' || $todayResult !== 'XX',
            'open_pana' => null,
            'close_pana' => null,
            'jodi' => $todayResult !== 'XX' ? $todayResult : ($last !== 'XX' ? $last : null),
            'open_ank' => null,
            'close_ank' => null,
            'result_string' => $todayResult !== 'XX' ? $todayResult : $last,
            'full_result' => $last.' | '.$todayResult,
            'cases' => [
                'open' => ['label' => 'Last', 'value' => $last !== 'XX' ? $last : null, 'ank' => null, 'display' => $last],
                'jodi' => ['label' => 'Today', 'value' => $todayResult !== 'XX' ? $todayResult : null, 'ank' => null, 'display' => $todayResult],
                'close' => ['label' => 'Close', 'value' => null, 'ank' => null, 'display' => '***'],
            ],
            'status' => $todayResult !== 'XX' ? 'closed' : 'pending',
            'status_label' => $todayResult !== 'XX' ? 'Declared' : 'Awaiting',
            'open_time' => $time,
            'close_time' => $time,
            'is_complete' => $todayResult !== 'XX',
            'chart_url' => $row['chart_url'] ?? 'https://satta-king-fast.com/',
            'highlight' => (bool) ($row['highlight'] ?? false),
            'prize' => null,
            'winners' => null,
            'numbers' => array_values(array_filter([$last !== 'XX' ? $last : null, $todayResult !== 'XX' ? $todayResult : null])),
            'yesterday' => null,
            'today' => null,
            'last_result' => $last,
            'last_result_date' => $last !== 'XX' ? $yesterday : null,
            'today_result' => $todayResult,
            'api_missing' => false,
        ];
    }
}

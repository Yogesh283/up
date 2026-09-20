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
        return Cache::remember('satta_king_fast.board.live', 15, function () {
            $parsed = $this->fetchAndParse();

            if (($parsed['rows'] ?? []) !== []) {
                Cache::put('satta_king_fast.board.last_ok', $parsed, now()->addHours(6));

                return $parsed;
            }

            $cached = Cache::get('satta_king_fast.board.last_ok');
            if (is_array($cached) && ($cached['rows'] ?? []) !== []) {
                $cached['error'] = ($parsed['error'] ?? 'Fetch failed').' · showing last cached board';

                return $cached;
            }

            return $parsed;
        });
    }

    /**
     * @return array{rows: list<array<string, mixed>>, today_date: ?string, yesterday_date: ?string, error: ?string}
     */
    protected function fetchAndParse(): array
    {
        $base = rtrim((string) config('services.satta_king_fast.base_url', 'https://satta-king-fast.com'), '/');
        $empty = [
            'rows' => [],
            'today_date' => now('Asia/Kolkata')->toDateString(),
            'yesterday_date' => now('Asia/Kolkata')->subDay()->toDateString(),
            'error' => null,
        ];

        // 1) Direct HTML (works when Cloudflare is not blocking the server IP)
        $direct = $this->httpGet($base.'/', [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => $base.'/',
        ]);

        if ($direct['ok'] && ! $this->isCloudflareChallenge($direct['body'])) {
            $parsed = $this->parseHtml($direct['body']);
            if ($parsed['rows'] !== []) {
                return $parsed;
            }
        }

        // 2) Reader proxy (bypasses Cloudflare JS challenge; returns markdown)
        $proxyBase = rtrim((string) config('services.satta_king_fast.proxy_url', 'https://r.jina.ai'), '/');
        $proxy = $this->httpGet($proxyBase.'/https://satta-king-fast.com/', [
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/plain,text/markdown,*/*',
        ], 40);

        if ($proxy['ok']) {
            $parsed = $this->parseMarkdown($proxy['body']);
            if ($parsed['rows'] !== []) {
                return $parsed;
            }

            // Sometimes proxy still embeds enough HTML-ish content
            $parsedHtml = $this->parseHtml($proxy['body']);
            if ($parsedHtml['rows'] !== []) {
                return $parsedHtml;
            }
        }

        $status = $direct['status'] ?? $proxy['status'] ?? 0;
        $empty['error'] = $this->isCloudflareChallenge($direct['body'] ?? '')
            ? 'Cloudflare blocked direct fetch (HTTP '.($direct['status'] ?? 403).'); proxy also failed'
            : 'Unable to load satta-king-fast.com (HTTP '.$status.')';

        return $empty;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{ok:bool,status:int,body:string}
     */
    protected function httpGet(string $url, array $headers = [], int $timeout = 20): array
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders($headers)
                ->get($url);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('SattaKingFast HTTP error', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 0,
                'body' => $e->getMessage(),
            ];
        }
    }

    protected function isCloudflareChallenge(string $body): bool
    {
        return str_contains($body, 'Just a moment...')
            || str_contains($body, 'cf-browser-verification')
            || str_contains($body, 'cf-challenge')
            || str_contains($body, 'Attention Required! | Cloudflare');
    }

    /**
     * Parse jina.ai / markdown mirror of the results table.
     *
     * @return array{rows: list<array<string, mixed>>, today_date: ?string, yesterday_date: ?string, error: ?string}
     */
    protected function parseMarkdown(string $markdown): array
    {
        $today = now('Asia/Kolkata')->toDateString();
        $yesterday = now('Asia/Kolkata')->subDay()->toDateString();

        if (preg_match('/Satta King (?:Fast )?Results? of ([A-Za-z]+ \d{1,2}, \d{4})/i', $markdown, $m)
            || preg_match('/Satta King Result of (\d{1,2})\w{0,2} ([A-Za-z]+) (\d{4})/i', $markdown, $m2)) {
            try {
                if (isset($m[1])) {
                    $parsed = Carbon::parse($m[1], 'Asia/Kolkata');
                } else {
                    $parsed = Carbon::parse($m2[1].' '.$m2[2].' '.$m2[3], 'Asia/Kolkata');
                }
                $today = $parsed->toDateString();
                $yesterday = $parsed->copy()->subDay()->toDateString();
            } catch (\Throwable) {
                // keep defaults
            }
        }

        $rows = [];
        // Example line:
        // | ### DESAWAR ### at 05:00 AM ### [Record Chart](https://...) | ### 35 | ### 32 |
        $pattern = '/\|\s*###\s*([^#|]+?)\s*###\s*at\s*([^#|]+?)\s*###\s*\[Record Chart\]\(([^)]+)\)\s*\|\s*###\s*([^#|]+?)\s*\|\s*###\s*([^#|]+?)\s*\|/iu';

        if (preg_match_all($pattern, $markdown, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $name = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));
                $time = trim($match[2]);
                $chart = trim($match[3]);
                $last = $this->normalizeResult(trim($match[4]));
                $todayVal = $this->normalizeResult(trim($match[5]));

                if ($name === '' || str_contains(mb_strtoupper($name), 'SHOW YOUR GAME')) {
                    continue;
                }

                $code = strtoupper(substr(preg_replace('/\W+/', '', $name) ?: 'X', 0, 4));
                if (preg_match('#/([a-z0-9-]+)/?$#i', $chart, $cm)) {
                    $code = strtoupper($cm[1]);
                }

                $rows[] = [
                    'code' => $code,
                    'name' => $name,
                    'time' => $time !== '' ? $time : '—',
                    'chart_url' => $chart !== '' ? $chart : 'https://satta-king-fast.com/',
                    'yesterday' => $last,
                    'today' => $todayVal,
                    'highlight' => in_array(strtoupper($name), $this->featuredNames, true),
                ];
            }
        }

        return [
            'rows' => $rows,
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'error' => $rows === [] ? 'No markets parsed from satta-king-fast mirror' : null,
        ];
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

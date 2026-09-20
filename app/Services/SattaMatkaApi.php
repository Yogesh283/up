<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SattaMatkaApi
{
    /**
     * Classic India-level market name/slug hints (shown first).
     *
     * @var list<string>
     */
    protected array $indiaHints = [
        'kalyan',
        'milan',
        'rajdhani',
        'main-bazar',
        'main bazar',
        'time-bazar',
        'time bazar',
        'sridevi',
        'madhur',
        'maharani',
        'karnataka',
        'tara-mumbai',
        'tara mumbai',
        'diamond',
        'main-sridevi',
        'main sridevi',
        'new-time',
        'night-time',
        'puna',
        'bombay',
        'mumbai',
        'prabhat',
        'mahakal',
        'parel',
        'banglore',
        'bangalore',
        'delhi',
        'ghaziabad',
        'gaziabad',
        'ganesh',
        'desawar',
        'faridabad',
        'gali',
        'noida',
    ];

    /**
     * Regional Satta King style markets — always pinned at top.
     * Some exist in SMA (delhi-bazar); others are not in API yet (show XX).
     *
     * @var list<array{slug:string,name:string,open_time:string,close_time:string}>
     */
    protected array $featuredRegional = [
        [
            'slug' => 'desawar',
            'name' => 'DESAWAR',
            'open_time' => '05:00 AM',
            'close_time' => '05:00 AM',
        ],
        [
            'slug' => 'delhi-bazar',
            'name' => 'DELHI BAZAR',
            'open_time' => '03:10 PM',
            'close_time' => '09:10 PM',
        ],
        [
            'slug' => 'shri-ganesh',
            'name' => 'SHRI GANESH',
            'open_time' => '04:30 PM',
            'close_time' => '04:30 PM',
        ],
        [
            'slug' => 'faridabad',
            'name' => 'FARIDABAD',
            'open_time' => '06:00 PM',
            'close_time' => '06:00 PM',
        ],
        [
            'slug' => 'ghaziabad',
            'name' => 'GHAZIABAD',
            'open_time' => '08:30 PM',
            'close_time' => '08:30 PM',
        ],
        [
            'slug' => 'gali',
            'name' => 'GALI',
            'open_time' => '11:15 PM',
            'close_time' => '11:15 PM',
        ],
        [
            'slug' => 'noida-king',
            'name' => 'NOIDA KING',
            'open_time' => '01:15 AM',
            'close_time' => '01:15 AM',
        ],
    ];

    /**
     * @return array{rows: list<array<string, mixed>>, error: ?string}
     */
    public function board(?string $date = null): array
    {
        $payload = $this->getJson('/api/results/board', array_filter([
            'date' => $date,
        ]));

        if ($payload['error'] && empty($payload['data'])) {
            return ['rows' => [], 'error' => $payload['error']];
        }

        $rows = is_array($payload['data']) ? $payload['data'] : [];

        return [
            'rows' => $rows,
            'error' => $payload['error'],
        ];
    }

    /**
     * Today + yesterday board: last result always, today XX until declared.
     *
     * @return array<string, mixed>
     */
    public function toResultsPayload(?string $date = null): array
    {
        $today = $date ?: now('Asia/Kolkata')->toDateString();
        $yesterday = \Carbon\Carbon::parse($today, 'Asia/Kolkata')->subDay()->toDateString();
        $orders = $this->displayOrders();

        $todayBoard = $this->board($today);
        $yesterdayBoard = $this->board($yesterday);

        $todayMapped = $this->mapBoardRows($todayBoard['rows'], $today, $orders)
            ->keyBy(fn (array $row) => $this->marketKey($row));
        $yesterdayMapped = $this->mapBoardRows($yesterdayBoard['rows'], $yesterday, $orders)
            ->keyBy(fn (array $row) => $this->marketKey($row));

        $keys = $todayMapped->keys()->merge($yesterdayMapped->keys())->unique()->values();

        $merged = $keys->map(function (string $key) use ($todayMapped, $yesterdayMapped) {
            $todayRow = $todayMapped->get($key);
            $yesterdayRow = $yesterdayMapped->get($key);
            $base = $todayRow ?? $yesterdayRow;
            if (! $base) {
                return null;
            }

            $lastDisplay = $this->dayDisplay($yesterdayRow, always: true);
            $todayDisplay = $this->dayDisplay($todayRow, always: false);

            return array_merge($base, [
                'yesterday' => $yesterdayRow,
                'today' => $todayRow,
                'last_result' => $lastDisplay,
                'today_result' => $todayDisplay,
                'has_result' => ($todayRow['has_result'] ?? false) || ($yesterdayRow['has_result'] ?? false),
            ]);
        })->filter()->values();

        $sorted = $merged
            ->sort(function (array $a, array $b) {
                if (($a['is_india'] ? 1 : 0) !== ($b['is_india'] ? 1 : 0)) {
                    return ($b['is_india'] ? 1 : 0) <=> ($a['is_india'] ? 1 : 0);
                }

                $oa = $a['display_order'] ?? 9999;
                $ob = $b['display_order'] ?? 9999;
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            })
            ->values();

        $sorted = $this->pinFeaturedRegionals($sorted, $today, $yesterday);

        $india = $sorted->where('is_india', true)->values();
        $others = $sorted->where('is_india', false)->values();
        $featured = $sorted->where('is_featured', true)->values();
        $declaredToday = $sorted->filter(fn (array $r) => ($r['today_result'] ?? 'XX') !== 'XX')->values();

        $latest = $sorted
            ->filter(fn (array $r) => ($r['today']['has_result'] ?? false) || ($r['yesterday']['has_result'] ?? false))
            ->sortByDesc(function (array $r) {
                $t = $r['today']['drawn_at'] ?? null;
                $y = $r['yesterday']['drawn_at'] ?? null;
                $ts = max(
                    $t ? (strtotime((string) $t) ?: 0) : 0,
                    $y ? (strtotime((string) $y) ?: 0) : 0,
                );

                return $ts;
            })
            ->map(function (array $r) {
                $src = ($r['today']['has_result'] ?? false) ? $r['today'] : $r['yesterday'];

                return array_merge($src ?? $r, [
                    'last_result' => $r['last_result'],
                    'today_result' => $r['today_result'],
                    'display_value' => ($r['today']['has_result'] ?? false)
                        ? $r['today_result']
                        : $r['last_result'],
                ]);
            })
            ->first();

        $error = $todayBoard['error'] ?? $yesterdayBoard['error'];

        return [
            'latest' => $latest,
            'results' => $sorted->all(),
            'india_results' => $india->all(),
            'other_results' => $others->all(),
            'featured_results' => $featured->all(),
            'declared' => $declaredToday->all(),
            'date' => $today,
            'today_date' => $today,
            'yesterday_date' => $yesterday,
            'today_label' => $this->shortDayLabel($today),
            'yesterday_label' => $this->shortDayLabel($yesterday),
            'banner_text' => sprintf(
                'Fast Results of %s & %s',
                \Carbon\Carbon::parse($today)->format('F j, Y'),
                \Carbon\Carbon::parse($yesterday)->format('F j, Y'),
            ),
            'source' => 'sattamatkaapi.live',
            'counts' => [
                'total' => $sorted->count(),
                'featured' => $featured->count(),
                'india' => $india->count(),
                'other' => $others->count(),
                'declared' => $declaredToday->count(),
                'full' => $declaredToday->where('is_complete', true)->count(),
                'open' => $sorted->filter(fn ($r) => ($r['today']['status'] ?? '') === 'open')->count(),
                'pending' => $sorted->filter(fn ($r) => ($r['today_result'] ?? 'XX') === 'XX')->count(),
                'holiday' => $sorted->filter(fn ($r) => in_array($r['today']['status'] ?? '', ['holiday', 'off_today'], true))->count(),
            ],
            'error' => $error,
        ];
    }

    /**
     * Pin Desawar / Delhi Bazar / Shri Ganesh / Ghaziabad etc. at the top.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $sorted
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function pinFeaturedRegionals($sorted, string $today, string $yesterday)
    {
        $bySlug = $sorted->keyBy(fn (array $row) => strtolower((string) ($row['slug'] ?? '')));
        $byName = $sorted->keyBy(fn (array $row) => strtolower(trim((string) ($row['name'] ?? ''))));

        $featured = collect();
        $usedKeys = [];

        foreach ($this->featuredRegional as $index => $market) {
            $slug = strtolower($market['slug']);
            $name = strtolower($market['name']);

            $existing = $bySlug->get($slug)
                ?? $byName->get($name)
                ?? $byName->get(strtolower(str_replace('-', ' ', $slug)));

            if ($existing) {
                $row = array_merge($existing, [
                    'is_featured' => true,
                    'is_india' => true,
                    'is_regional' => true,
                    'featured_order' => $index,
                    'name' => $market['name'],
                    'open_time' => $existing['open_time'] ?: $market['open_time'],
                    'close_time' => $existing['close_time'] ?: $market['close_time'],
                ]);
                $usedKeys[] = $this->marketKey($existing);
            } else {
                $row = $this->makeRegionalStub($market, $today, $yesterday, $index);
            }

            $featured->push($row);
        }

        $rest = $sorted
            ->reject(fn (array $row) => in_array($this->marketKey($row), $usedKeys, true))
            ->reject(function (array $row) {
                $slug = strtolower((string) ($row['slug'] ?? ''));
                $name = strtolower((string) ($row['name'] ?? ''));

                foreach ($this->featuredRegional as $market) {
                    if ($slug === strtolower($market['slug']) || $name === strtolower($market['name'])) {
                        return true;
                    }
                }

                return false;
            })
            ->map(fn (array $row) => array_merge($row, [
                'is_featured' => false,
                'featured_order' => 9999,
            ]))
            ->values();

        return $featured->concat($rest)->values();
    }

    /**
     * @param  array{slug:string,name:string,open_time:string,close_time:string}  $market
     * @return array<string, mixed>
     */
    protected function makeRegionalStub(array $market, string $today, string $yesterday, int $order): array
    {
        $base = [
            'id' => 'regional-'.$market['slug'],
            'slug' => $market['slug'],
            'name' => $market['name'],
            'date' => $today,
            'drawn_at' => null,
            'display_order' => $order,
            'featured_order' => $order,
            'is_india' => true,
            'is_featured' => true,
            'is_regional' => true,
            'has_result' => false,
            'open_pana' => null,
            'close_pana' => null,
            'jodi' => null,
            'open_ank' => null,
            'close_ank' => null,
            'result_string' => null,
            'full_result' => '***-***-***',
            'cases' => [
                'open' => ['label' => 'Open', 'value' => null, 'ank' => null, 'display' => '***'],
                'jodi' => ['label' => 'Jodi', 'value' => null, 'ank' => null, 'display' => '***'],
                'close' => ['label' => 'Close', 'value' => null, 'ank' => null, 'display' => '***'],
            ],
            'status' => 'pending',
            'status_label' => 'Awaiting API market',
            'open_time' => $market['open_time'],
            'close_time' => $market['close_time'],
            'is_complete' => false,
            'prize' => null,
            'winners' => null,
            'numbers' => [],
            'yesterday' => null,
            'today' => null,
            'last_result' => 'XX',
            'today_result' => 'XX',
            'api_missing' => true,
        ];

        return $base;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function marketKey(array $row): string
    {
        return strtolower((string) ($row['slug'] ?? $row['name'] ?? $row['id'] ?? uniqid('m', true)));
    }

    /**
     * Single-cell display for a day column.
     * Today: XX until declared. Last day: always show best available (or XX).
     *
     * @param  array<string, mixed>|null  $row
     */
    protected function dayDisplay(?array $row, bool $always): string
    {
        if (! $row) {
            return 'XX';
        }

        $has = (bool) ($row['has_result'] ?? false);
        if (! $always && ! $has) {
            return 'XX';
        }

        $jodi = $this->strOrNull($row['jodi'] ?? null);
        if ($jodi && ! str_contains($jodi, '*') && preg_match('/^\d{2}$/', $jodi)) {
            return $jodi;
        }

        $openAnk = $this->strOrNull($row['open_ank'] ?? null);
        $closeAnk = $this->strOrNull($row['close_ank'] ?? null);
        if ($openAnk !== null && $closeAnk !== null) {
            return $openAnk.$closeAnk;
        }

        if ($jodi) {
            $clean = preg_replace('/\D/', '', $jodi) ?: '';
            if (strlen($clean) >= 2) {
                return substr($clean, 0, 2);
            }
            if ($always && $openAnk !== null) {
                return str_pad($openAnk, 2, '0', STR_PAD_LEFT);
            }
        }

        if ($always && $openAnk !== null) {
            return str_pad($openAnk, 2, '0', STR_PAD_LEFT);
        }

        $resultString = $this->strOrNull($row['result_string'] ?? null);
        if ($always && $resultString) {
            $parts = preg_split('/[-–]/', $resultString) ?: [];
            $first = preg_replace('/\D/', '', (string) ($parts[0] ?? '')) ?: '';
            if ($first !== '') {
                return strlen($first) === 1 ? str_pad($first, 2, '0', STR_PAD_LEFT) : substr($first, 0, 2);
            }
        }

        return 'XX';
    }

    protected function shortDayLabel(string $date): string
    {
        return \Carbon\Carbon::parse($date, 'Asia/Kolkata')->format('D. jS');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $orders
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function mapBoardRows(array $rows, string $date, array $orders)
    {
        return collect($rows)
            ->map(fn (array $row) => $this->mapMarket($row, $date, $orders))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, int>
     */
    protected function displayOrders(): array
    {
        return Cache::remember('sma.market_display_orders', 3600, function () {
            $payload = $this->getJson('/api/markets/catalog');
            $data = is_array($payload['data']) ? $payload['data'] : [];
            $map = [];

            foreach ($data as $market) {
                if (! is_array($market)) {
                    continue;
                }
                $slug = (string) ($market['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }
                $map[$slug] = (int) ($market['displayOrder'] ?? 9999);
            }

            return $map;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function resultRank(array $item): int
    {
        if ($item['is_complete'] ?? false) {
            return 100;
        }
        if (($item['cases']['close']['value'] ?? null) && ($item['cases']['open']['value'] ?? null)) {
            return 80;
        }
        if ($item['has_result'] ?? false) {
            return 60;
        }
        if (($item['status'] ?? '') === 'open') {
            return 40;
        }
        if (($item['status'] ?? '') === 'pending') {
            return 20;
        }

        return 0;
    }

    protected function isIndiaMarket(string $slug, string $name, ?int $displayOrder): bool
    {
        $hay = strtolower(trim($slug.' '.$name));

        foreach ($this->indiaHints as $hint) {
            if (str_contains($hay, $hint)) {
                return true;
            }
        }

        // Top catalog order markets are treated as India main board.
        return $displayOrder !== null && $displayOrder > 0 && $displayOrder <= 80;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $orders
     * @return array<string, mixed>|null
     */
    protected function mapMarket(array $row, ?string $date = null, array $orders = []): ?array
    {
        $nested = is_array($row['result'] ?? null) ? $row['result'] : [];

        $id = $row['marketId'] ?? $row['id'] ?? null;
        $name = $row['name']
            ?? $row['marketName']
            ?? $row['canonicalName']
            ?? $row['market']
            ?? null;

        if (! $name) {
            return null;
        }

        $slug = (string) ($row['slug'] ?? '');
        $openPana = $this->strOrNull($row['openPana'] ?? $nested['openPana'] ?? null);
        $closePana = $this->strOrNull($row['closePana'] ?? $nested['closePana'] ?? null);
        $jodi = $this->strOrNull($row['jodi'] ?? $nested['jodi'] ?? null);
        $openAnk = $this->strOrNull($row['openAnk'] ?? $nested['openAnk'] ?? null);
        $closeAnk = $this->strOrNull($row['closeAnk'] ?? $nested['closeAnk'] ?? null);
        $resultString = $this->strOrNull($row['resultString'] ?? $nested['resultString'] ?? null);

        $fullResult = $this->buildFullResult($openPana, $jodi, $closePana, $resultString);

        $drawnAt = $row['publishedAt']
            ?? $row['resultTimestamp']
            ?? $row['boardTimestamp']
            ?? $row['updatedAt']
            ?? $nested['publishedAt']
            ?? null;

        $resultDate = $row['resultDate'] ?? $row['marketDate'] ?? $date;

        if (! $drawnAt && $resultDate) {
            $drawnAt = $resultDate.'T00:00:00+05:30';
        }

        $status = $row['status'] ?? $row['boardState'] ?? 'pending';
        $displayOrder = $orders[$slug] ?? null;
        $isIndia = $this->isIndiaMarket($slug, (string) $name, $displayOrder);
        $hasResult = filled($openPana) || filled($jodi) || filled($closePana) || filled($resultString);
        $isComplete = (bool) ($row['isComplete'] ?? $nested['isComplete'] ?? false)
            || (filled($openPana) && filled($jodi) && filled($closePana) && ! str_contains((string) $jodi, '*'));

        return [
            'id' => $id ?? ($slug !== '' ? $slug : $name),
            'slug' => $slug !== '' ? $slug : null,
            'name' => $name,
            'date' => $resultDate,
            'drawn_at' => $drawnAt,
            'display_order' => $displayOrder ?? 9999,
            'is_india' => $isIndia,
            'has_result' => $hasResult,
            'open_pana' => $openPana,
            'close_pana' => $closePana,
            'jodi' => $jodi,
            'open_ank' => $openAnk,
            'close_ank' => $closeAnk,
            'result_string' => $resultString ?: ($hasResult ? str_replace('*', '', $fullResult) : null),
            'full_result' => $fullResult,
            // Systematic three cases
            'cases' => [
                'open' => [
                    'label' => 'Open',
                    'value' => $openPana,
                    'ank' => $openAnk,
                    'display' => $openPana ?: '***',
                ],
                'jodi' => [
                    'label' => 'Jodi',
                    'value' => $jodi,
                    'ank' => null,
                    'display' => $jodi ?: '***',
                ],
                'close' => [
                    'label' => 'Close',
                    'value' => $closePana,
                    'ank' => $closeAnk,
                    'display' => $closePana ?: '***',
                ],
            ],
            'status' => $status,
            'status_label' => $row['statusLabel'] ?? $row['boardLabel'] ?? null,
            'open_time' => $row['openTime'] ?? null,
            'close_time' => $row['closeTime'] ?? null,
            'is_complete' => $isComplete,
            'prize' => null,
            'winners' => null,
            'numbers' => array_values(array_filter([
                $openPana,
                $jodi,
                $closePana,
            ], fn ($v) => filled($v))),
        ];
    }

    protected function buildFullResult(?string $open, ?string $jodi, ?string $close, ?string $resultString): string
    {
        if ($resultString && filled($open) && filled($close)) {
            return $resultString;
        }

        return ($open ?: '***').'-'.($jodi ?: '***').'-'.($close ?: '***');
    }

    protected function strOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{data: mixed, error: ?string}
     */
    protected function getJson(string $path, array $query = []): array
    {
        $base = rtrim((string) config('services.sattamatka.base_url'), '/');
        $url = $base.$path;

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'UpDown/1.0',
        ];

        $apiKey = trim((string) config('services.sattamatka.api_key', ''));
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
            $headers['X-API-Key'] = $apiKey;
        }

        try {
            $response = Http::timeout(20)
                ->retry(2, 250)
                ->withHeaders($headers)
                ->acceptJson()
                ->get($url, $query);

            if (! $response->successful()) {
                $error = 'SMA HTTP '.$response->status();
                Log::warning('SattaMatka request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return ['data' => [], 'error' => $error];
            }

            $json = $response->json();

            return [
                'data' => $json['data'] ?? $json,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('SattaMatka exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return ['data' => [], 'error' => $e->getMessage()];
        }
    }
}

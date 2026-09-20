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
     * Last day board: India markets first, full Open/Jodi/Close cases, latest on top.
     *
     * @return array<string, mixed>
     */
    public function toResultsPayload(?string $date = null): array
    {
        $explicitDate = $date;
        $date = $date ?: now('Asia/Kolkata')->toDateString();
        $orders = $this->displayOrders();

        $board = $this->board($date);
        $mapped = $this->mapBoardRows($board['rows'], $date, $orders);

        if ($mapped->filter(fn (array $i) => $i['has_result'])->isEmpty() && $explicitDate === null) {
            $previous = now('Asia/Kolkata')->subDay()->toDateString();
            $prevBoard = $this->board($previous);
            $mapped = $this->mapBoardRows($prevBoard['rows'], $previous, $orders);
            $date = $previous;
            $board['error'] = $board['error'] ?? $prevBoard['error'];
        }

        $sorted = $mapped
            ->sort(function (array $a, array $b) {
                // India markets first
                if (($a['is_india'] ? 1 : 0) !== ($b['is_india'] ? 1 : 0)) {
                    return ($b['is_india'] ? 1 : 0) <=> ($a['is_india'] ? 1 : 0);
                }

                // Then declared / fuller results
                $ra = $this->resultRank($a);
                $rb = $this->resultRank($b);
                if ($ra !== $rb) {
                    return $rb <=> $ra;
                }

                // Catalog display order
                $oa = $a['display_order'] ?? 9999;
                $ob = $b['display_order'] ?? 9999;
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }

                $ta = strtotime((string) ($a['drawn_at'] ?? '')) ?: 0;
                $tb = strtotime((string) ($b['drawn_at'] ?? '')) ?: 0;
                if ($tb !== $ta) {
                    return $tb <=> $ta;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            })
            ->values();

        $india = $sorted->where('is_india', true)->values();
        $others = $sorted->where('is_india', false)->values();
        $declared = $sorted->where('has_result', true)->values();

        $latest = $declared
            ->sortByDesc(fn (array $i) => strtotime((string) ($i['drawn_at'] ?? '')) ?: 0)
            ->first();

        return [
            'latest' => $latest,
            'results' => $sorted->all(),
            'india_results' => $india->all(),
            'other_results' => $others->all(),
            'declared' => $declared->all(),
            'date' => $date,
            'source' => 'sattamatkaapi.live',
            'counts' => [
                'total' => $sorted->count(),
                'india' => $india->count(),
                'other' => $others->count(),
                'declared' => $declared->count(),
                'full' => $declared->where('is_complete', true)->count(),
                'open' => $sorted->where('status', 'open')->count(),
                'pending' => $sorted->where('status', 'pending')->count(),
                'holiday' => $sorted->whereIn('status', ['holiday', 'off_today'])->count(),
            ],
            'error' => $board['error'],
        ];
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

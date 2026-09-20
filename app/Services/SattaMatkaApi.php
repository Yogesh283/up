<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SattaMatkaApi
{
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
     * @return array{latest: ?array<string, mixed>, results: list<array<string, mixed>>, source: string, counts: array<string, int>, error: ?string}
     */
    public function toResultsPayload(?string $date = null): array
    {
        $board = $this->board($date);
        $mapped = collect($board['rows'])
            ->map(fn (array $row) => $this->mapMarket($row))
            ->filter()
            ->values();

        $sorted = $mapped
            ->sort(function (array $a, array $b) {
                $rank = $this->rank($b) <=> $this->rank($a);
                if ($rank !== 0) {
                    return $rank;
                }

                return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            })
            ->values();

        $withResult = $sorted->filter(fn (array $item) => $this->hasDeclaredResult($item))->values();

        return [
            'latest' => $withResult->first() ?? $sorted->first(),
            'results' => $sorted->all(),
            'declared' => $withResult->all(),
            'source' => 'sattamatkaapi.live',
            'counts' => [
                'total' => $sorted->count(),
                'declared' => $withResult->count(),
                'open' => $sorted->where('status', 'open')->count(),
                'pending' => $sorted->where('status', 'pending')->count(),
                'holiday' => $sorted->whereIn('status', ['holiday', 'off_today'])->count(),
            ],
            'error' => $board['error'],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function hasDeclaredResult(array $item): bool
    {
        return filled($item['result_string'] ?? null)
            || filled($item['open_pana'] ?? null)
            || filled($item['jodi'] ?? null)
            || filled($item['close_pana'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function rank(array $item): int
    {
        if ($this->hasDeclaredResult($item) && ($item['is_complete'] ?? false)) {
            return 100;
        }
        if ($this->hasDeclaredResult($item)) {
            return 80;
        }
        if (($item['status'] ?? '') === 'open') {
            return 60;
        }
        if (($item['status'] ?? '') === 'pending') {
            return 40;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function mapMarket(array $row): ?array
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

        $openPana = $row['openPana'] ?? $nested['openPana'] ?? null;
        $closePana = $row['closePana'] ?? $nested['closePana'] ?? null;
        $jodi = $row['jodi'] ?? $nested['jodi'] ?? null;
        $resultString = $row['resultString'] ?? $nested['resultString'] ?? null;

        if (! $resultString && ($openPana || $jodi || $closePana)) {
            $resultString = collect([$openPana, $jodi, $closePana])
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->implode('-');
        }

        $drawnAt = $row['publishedAt']
            ?? $row['resultTimestamp']
            ?? $row['boardTimestamp']
            ?? $row['updatedAt']
            ?? $nested['publishedAt']
            ?? null;

        if (! $drawnAt && ! empty($row['resultDate'])) {
            $drawnAt = $row['resultDate'].'T00:00:00+05:30';
        }

        $status = $row['status'] ?? $row['boardState'] ?? 'pending';

        return [
            'id' => $id ?? $row['slug'] ?? $name,
            'slug' => $row['slug'] ?? null,
            'name' => $name,
            'drawn_at' => $drawnAt,
            'open_pana' => $openPana,
            'close_pana' => $closePana,
            'jodi' => $jodi,
            'result_string' => $resultString,
            'status' => $status,
            'status_label' => $row['statusLabel'] ?? $row['boardLabel'] ?? null,
            'open_time' => $row['openTime'] ?? null,
            'close_time' => $row['closeTime'] ?? null,
            'is_complete' => (bool) ($row['isComplete'] ?? $nested['isComplete'] ?? false),
            'prize' => null,
            'winners' => null,
            'numbers' => $this->digitsFromResult($resultString, $openPana, $jodi, $closePana),
        ];
    }

    /**
     * @return list<string>
     */
    protected function digitsFromResult(?string $resultString, mixed $openPana, mixed $jodi, mixed $closePana): array
    {
        $parts = collect([$openPana, $jodi, $closePana])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();

        if ($parts === [] && $resultString) {
            $parts = preg_split('/[-–]/', $resultString) ?: [];
            $parts = array_values(array_filter(array_map('trim', $parts)));
        }

        return $parts;
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

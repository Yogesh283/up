<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SattaMatkaApi
{
    public function board(?string $date = null): array
    {
        $base = rtrim((string) config('services.sattamatka.base_url'), '/');
        $url = $base.'/api/results/board';

        $headers = [
            'Accept' => 'application/json',
        ];

        $apiKey = config('services.sattamatka.api_key');
        if (is_string($apiKey) && $apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $query = [];
        if ($date) {
            $query['date'] = $date;
        }

        try {
            $response = Http::timeout(12)
                ->withHeaders($headers)
                ->get($url, $query);

            if (! $response->successful()) {
                Log::warning('SattaMatka board request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $payload = $response->json();

            return is_array($payload['data'] ?? null) ? $payload['data'] : [];
        } catch (\Throwable $e) {
            Log::warning('SattaMatka board exception', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Map board rows into Results page / dashboard shape.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{latest: ?array<string, mixed>, results: list<array<string, mixed>>}
     */
    public function toResultsPayload(array $rows): array
    {
        $mapped = collect($rows)
            ->map(fn (array $row) => $this->mapMarket($row))
            ->filter()
            ->values();

        $withResult = $mapped
            ->filter(fn (array $item) => filled($item['result_string']) || filled($item['jodi']) || filled($item['open_pana']))
            ->sortByDesc(fn (array $item) => $item['drawn_at'] ?? '')
            ->values();

        $list = $withResult->isNotEmpty()
            ? $withResult
            : $mapped->take(20)->values();

        $latest = $list->first();

        return [
            'latest' => $latest,
            'results' => $list->all(),
            'source' => 'sattamatkaapi.live',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    protected function mapMarket(array $row): ?array
    {
        $id = $row['marketId'] ?? $row['id'] ?? null;
        $name = $row['name'] ?? $row['marketName'] ?? $row['market'] ?? null;

        if (! $name) {
            return null;
        }

        $openPana = $row['openPana'] ?? null;
        $closePana = $row['closePana'] ?? null;
        $jodi = $row['jodi'] ?? null;
        $resultString = $row['resultString'] ?? null;

        if (! $resultString && ($openPana || $jodi || $closePana)) {
            $resultString = collect([$openPana, $jodi, $closePana])
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->implode('-');
        }

        $drawnAt = $row['publishedAt']
            ?? $row['resultTimestamp']
            ?? $row['boardTimestamp']
            ?? $row['updatedAt']
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
            'is_complete' => (bool) ($row['isComplete'] ?? false),
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
}

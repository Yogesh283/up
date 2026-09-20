<?php

namespace App\Services;

use App\Support\MarketSorter;

class CombinedResultsService
{
    public function __construct(
        protected SattaKingFastService $king,
        protected SattaKalyanMatkaService $matka,
    ) {}

    /**
     * Merge Satta King Fast + Kalyan Matka boards.
     *
     * @return array<string, mixed>
     */
    public function toResultsPayload(bool $settleBets = true): array
    {
        $king = $this->king->toResultsPayload();
        $matka = $this->matka->toResultsPayload();

        $errors = array_values(array_filter([
            $king['error'] ?? null,
            $matka['error'] ?? null,
        ]));

        $kingResults = collect($king['results'] ?? [])->map(function (array $row) {
            $row['source_type'] = $row['source_type'] ?? 'king';
            $row['board'] = 'king';

            return $row;
        })->all();
        $kingResults = MarketSorter::byUrgency($kingResults, 'king');

        $matkaResults = collect($matka['results'] ?? [])->map(function (array $row) {
            $row['source_type'] = 'matka';
            $row['board'] = 'matka';

            return $row;
        })->all();
        $matkaResults = MarketSorter::byUrgency($matkaResults, 'matka');

        // Default "results" stays King board (existing UI). Matka in separate key.
        $latestKing = collect($kingResults)->first(
            fn (array $r) => ($r['urgency_bucket'] ?? 1) <= 1
                && (($r['today_result'] ?? 'XX') !== 'XX' || ($r['is_due'] ?? false) || ($r['is_next_up'] ?? false))
        ) ?? ($king['latest'] ?? null);

        $latestMatka = collect($matkaResults)->first(
            fn (array $r) => ($r['urgency_bucket'] ?? 1) <= 1
        ) ?? ($matka['latest'] ?? null);

        if ($latestKing && is_array($latestKing)) {
            $latestKing = array_merge($latestKing, [
                'display_value' => (($latestKing['today_result'] ?? 'XX') !== 'XX')
                    ? $latestKing['today_result']
                    : ($latestKing['last_result'] ?? 'XX'),
            ]);
        }
        if ($latestMatka && is_array($latestMatka)) {
            $latestMatka = array_merge($latestMatka, [
                'display_value' => $latestMatka['full_result'] ?? $latestMatka['today_result'] ?? 'XX',
            ]);
        }

        $payload = [
            'latest' => $latestKing,
            'latest_matka' => $latestMatka,
            'results' => $kingResults,
            'king_results' => $kingResults,
            'matka_results' => $matkaResults,
            'featured_results' => $king['featured_results'] ?? [],
            'declared' => $king['declared'] ?? [],
            'date' => $king['date'] ?? $matka['date'] ?? now('Asia/Kolkata')->toDateString(),
            'today_date' => $king['today_date'] ?? null,
            'yesterday_date' => $king['yesterday_date'] ?? null,
            'today_label' => $king['today_label'] ?? 'Today',
            'yesterday_label' => $king['yesterday_label'] ?? 'Last',
            'yesterday_date_label' => $king['yesterday_date_label'] ?? null,
            'banner_text' => $king['banner_text'] ?? null,
            'matka_banner_text' => $matka['banner_text'] ?? null,
            'note' => null,
            'source' => 'satta-king-fast.com + sattakalyanmatka.net',
            'sources' => [
                'king' => $king['source'] ?? 'satta-king-fast.com',
                'matka' => $matka['source'] ?? 'sattakalyanmatka.net',
            ],
            'counts' => [
                'total' => count($kingResults) + count($matkaResults),
                'king' => count($kingResults),
                'matka' => count($matkaResults),
                'featured' => count($king['featured_results'] ?? []),
                'declared' => count($king['declared'] ?? []),
                'declared_last' => $king['counts']['declared_last'] ?? 0,
                'matka_declared' => $matka['counts']['declared'] ?? 0,
                'pending' => $king['counts']['pending'] ?? 0,
            ],
            'king' => [
                'banner_text' => $king['banner_text'] ?? null,
                'yesterday_label' => $king['yesterday_label'] ?? 'Last',
                'today_label' => $king['today_label'] ?? 'Today',
                'counts' => $king['counts'] ?? [],
            ],
            'matka' => [
                'banner_text' => $matka['banner_text'] ?? null,
                'counts' => $matka['counts'] ?? [],
            ],
            'error' => $errors === [] ? null : implode(' | ', $errors),
            'updated_at' => now('Asia/Kolkata')->toIso8601String(),
            'fingerprint' => md5(json_encode([
                collect($kingResults)->map(fn ($r) => [
                    $r['name'] ?? '',
                    $r['last_result'] ?? '',
                    $r['today_result'] ?? '',
                ])->all(),
                collect($matkaResults)->map(fn ($r) => [
                    $r['name'] ?? '',
                    $r['full_result'] ?? '',
                ])->all(),
            ])),
            'poll_seconds' => 15,
        ];

        if ($settleBets) {
            try {
                app(BetSettlementService::class)->settleFromPayload($payload);
            } catch (\Throwable) {
                // Settlement must never break results API.
            }
        }

        return $payload;
    }
}

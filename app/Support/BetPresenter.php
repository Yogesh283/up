<?php

namespace App\Support;

class BetPresenter
{
    /**
     * @param  \App\Models\Bet  $bet
     * @return array<string, mixed>
     */
    public static function toArray($bet): array
    {
        $digits = match ($bet->bet_type) {
            'single_open', 'single_close' => 1,
            'pana_open', 'pana_close' => 3,
            default => 2,
        };

        $typeMeta = self::typeMeta($bet->board, $bet->bet_type);
        $statusMeta = self::statusMeta($bet->status);
        $numbersDisplay = collect($bet->numbers ?? [])
            ->map(fn ($n) => str_pad((string) $n, $digits, '0', STR_PAD_LEFT))
            ->all();

        $multiplier = (float) ($typeMeta['multiplier'] ?? config('betting.king.multiplier', 9));
        $potential = round((float) $bet->amount * $multiplier, 2);

        $boardLabel = match ($bet->board) {
            'matka' => 'Kalyan Matka',
            'king' => 'Satta King',
            default => $bet->board ? ucfirst((string) $bet->board) : 'Market',
        };

        // Prefer market name without duplicated type suffix when possible
        $marketName = (string) $bet->draw_name;
        if (str_contains($marketName, ' · ')) {
            $marketName = explode(' · ', $marketName)[0];
        }

        return [
            'id' => $bet->id,
            'ref' => 'BET-'.$bet->id,
            'draw_name' => $bet->draw_name,
            'market_name' => $marketName,
            'board' => $bet->board,
            'board_label' => $boardLabel,
            'bet_type' => $bet->bet_type,
            'bet_type_label' => $typeMeta['label'],
            'bet_type_hint' => $typeMeta['hint'],
            'numbers' => $bet->numbers,
            'numbers_display' => $numbersDisplay,
            'number_text' => implode(', ', $numbersDisplay),
            'amount' => (float) $bet->amount,
            'prize' => (float) $bet->prize,
            'potential_win' => $potential,
            'multiplier' => $multiplier,
            'result_value' => $bet->result_value,
            'status' => $bet->status,
            'status_label' => $statusMeta['label'],
            'status_hint' => $statusMeta['hint'],
            'is_active' => $bet->status === 'pending',
            'draw_at' => optional($bet->draw_at)?->toIso8601String(),
            'settled_at' => optional($bet->settled_at)?->toIso8601String(),
            'created_at' => $bet->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{label:string,hint:string,multiplier:float}
     */
    public static function typeMeta(?string $board, ?string $type): array
    {
        if ($board === 'matka' || in_array($type, ['single_open', 'single_close', 'jodi', 'pana_open', 'pana_close'], true)) {
            $cfg = config('betting.matka.types.'.$type);

            if (is_array($cfg)) {
                return [
                    'label' => (string) $cfg['label'],
                    'hint' => (string) ($cfg['hint'] ?? ''),
                    'multiplier' => (float) $cfg['multiplier'],
                ];
            }
        }

        return [
            'label' => 'Number',
            'hint' => '00–99',
            'multiplier' => (float) config('betting.king.multiplier', 9),
        ];
    }

    /**
     * @return array{label:string,hint:string}
     */
    public static function statusMeta(?string $status): array
    {
        return match ($status) {
            'pending' => [
                'label' => 'Running',
                'hint' => 'Result ka wait — abhi settle nahi hua',
            ],
            'won' => [
                'label' => 'Won',
                'hint' => 'Jeet — amount wallet me add ho gaya',
            ],
            'lost' => [
                'label' => 'Lost',
                'hint' => 'Number match nahi hua',
            ],
            'refunded' => [
                'label' => 'Refunded',
                'hint' => 'Holiday / cancel — amount wapas',
            ],
            default => [
                'label' => ucfirst((string) $status),
                'hint' => '',
            ],
        };
    }
}

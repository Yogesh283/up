<?php

namespace App\Support;

class Draws
{
    /**
     * Available open draws for betting.
     *
     * @return list<array{id:int,name:string,draw_at:string,prize:string,ticket_price:float,ticket_price_display:string,pick_count:int,max_number:int,status:string}>
     */
    public static function open(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Evening Jackpot',
                'draw_at' => now()->addHours(5)->toIso8601String(),
                'prize' => '₹10,00,000',
                'ticket_price' => 50,
                'ticket_price_display' => '₹50',
                'pick_count' => 6,
                'max_number' => 49,
                'status' => 'open',
            ],
            [
                'id' => 2,
                'name' => 'Lucky 6',
                'draw_at' => now()->addHours(18)->toIso8601String(),
                'prize' => '₹2,50,000',
                'ticket_price' => 20,
                'ticket_price_display' => '₹20',
                'pick_count' => 6,
                'max_number' => 49,
                'status' => 'open',
            ],
        ];
    }

    public static function find(int $id): ?array
    {
        foreach (self::open() as $draw) {
            if ($draw['id'] === $id) {
                return $draw;
            }
        }

        return null;
    }
}

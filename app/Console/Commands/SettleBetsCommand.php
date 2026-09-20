<?php

namespace App\Console\Commands;

use App\Services\BetSettlementService;
use Illuminate\Console\Command;

class SettleBetsCommand extends Command
{
    protected $signature = 'bets:settle';

    protected $description = 'Settle pending Satta King / Matka bets against live results (King 90×)';

    public function handle(BetSettlementService $settlement): int
    {
        $stats = $settlement->settle();

        $this->info(sprintf(
            'Settled %d · won %d · lost %d · refunded %d · credited ₹%s',
            $stats['settled'],
            $stats['won'],
            $stats['lost'],
            $stats['refunded'],
            number_format($stats['credited'], 2),
        ));

        return self::SUCCESS;
    }
}

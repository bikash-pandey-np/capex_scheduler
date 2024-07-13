<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateActiveTrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trade:update-active';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update active trade every 5 seconds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating active trade...');

        $activeTrades = DB::table('positions')->where('is_active', true)->get();

        if ($activeTrades->isEmpty()) {
            $this->info('No active trades.');
        } else {
            foreach ($activeTrades as $trade) {
                $this->info('Updating trade ID: ' . $trade->id);

                // Assuming getCurrentPrice is a helper function to get the current price of the trade
                $currentPrice = getCurrentPrice($trade);

                if (now()->greaterThan($trade->will_close_at)) {
                    // Settle the trade
                    $this->info('SETTELING TRADE ' . $trade->id);
                    settleTrade($trade, $currentPrice);
                    $this->info('TRADE SETTLED WITH ID = ' . $trade->id . ' Trade Identifier = ' . $trade->identifier);

                } else {
                    // Update ongoing trade status
                    if ($trade->status === 'Not Settled') {
                        $this->info('UPDATING PNL For Trade ID =' . $trade->id . ' Identifier = ' . $trade->identifier);
                        updateOngoingTrade($trade, $currentPrice);
                        $this->info('PNL Updated for Trade ID =' . $trade->id . ' Identifier = ' . $trade->identifier);

                    }
                }
            }
        }
    }
}

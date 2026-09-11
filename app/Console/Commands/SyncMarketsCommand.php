<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Services\Exchanges\ExchangeFactory;

class SyncMarketsCommand extends Command
{
    protected $signature = 'trading:sync-markets';
    protected $description = 'Sync market data from connected exchanges';

    public function handle()
    {
        $exchanges = ExchangeAccount::where('status', 'connected')->get();

        foreach ($exchanges as $account) {
            $this->info("Syncing markets for {$account->exchange}...");

            try {
                $credentials = $account->getDecryptedCredentials();
                $adapter = ExchangeFactory::make($account->exchange, $credentials);
                $markets = $adapter->getMarkets('spot');

                foreach ($markets as $market) {
                    ExchangeMarket::updateOrCreate(
                        [
                            'exchange' => $account->exchange,
                            'symbol' => $market['symbol'],
                            'market_type' => 'spot',
                        ],
                        [
                            'base_asset' => $market['base_asset'],
                            'quote_asset' => $market['quote_asset'],
                            'price_precision' => $market['price_precision'],
                            'quantity_precision' => $market['quantity_precision'],
                            'min_quantity' => $market['min_quantity'],
                            'tick_size' => $market['tick_size'],
                            'status' => $market['status'],
                        ]
                    );
                }

                $this->info("  Synced " . count($markets) . " spot markets");
            } catch (\Exception $e) {
                $this->error("  Failed: " . $e->getMessage());
            }
        }

        $this->info('Market sync complete.');
        return 0;
    }
}

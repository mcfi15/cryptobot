<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TradingBot;
use App\Models\BotSignal;
use App\Models\BotTrade;
use App\Models\ExchangeAccount;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Trading\SignalEngine;
use App\Services\Risk\SpotRiskEngine;
use App\Services\Risk\FuturesRiskEngine;

class RunSignalsCommand extends Command
{
    protected $signature = 'trading:run-signals';
    protected $description = 'Run signal generation for all active bots';

    protected SignalEngine $signalEngine;
    protected SpotRiskEngine $spotRisk;
    protected FuturesRiskEngine $futuresRisk;

    public function __construct(SignalEngine $signalEngine, SpotRiskEngine $spotRisk, FuturesRiskEngine $futuresRisk)
    {
        parent::__construct();
        $this->signalEngine = $signalEngine;
        $this->spotRisk = $spotRisk;
        $this->futuresRisk = $futuresRisk;
    }

    public function handle()
    {
        $bots = TradingBot::where('status', 'running')->with('exchangeAccount')->get();

        foreach ($bots as $bot) {
            $this->info("Processing bot: {$bot->name}");

            try {
                $credentials = $bot->exchangeAccount->getDecryptedCredentials();
                $adapter = ExchangeFactory::make($bot->exchangeAccount->exchange, $credentials);

                foreach ($bot->symbols as $symbol) {
                    $candles = $adapter->getKlines($symbol, $bot->timeframes[0] ?? '4h', 200);
                    if (count($candles) < 50) continue;

                    $signal = $this->signalEngine->generateSignal($candles, $bot->strategy);
                    if (!$signal) continue;

                    BotSignal::create([
                        'bot_id' => $bot->id,
                        'symbol' => $symbol,
                        'market_type' => $bot->market_type,
                        'direction' => $signal['direction'],
                        'confidence' => $signal['confidence'],
                        'signal_score' => $signal['score'],
                        'entry_price' => $signal['entry_price'],
                        'stop_loss' => $signal['stop_loss'],
                        'take_profit' => $signal['take_profit'],
                        'risk_reward' => $signal['risk_reward'],
                        'strategy' => $signal['strategy'],
                        'market_regime' => $signal['market_regime'],
                        'features' => $signal['indicators'],
                        'status' => $signal['score'] >= $bot->signal_threshold ? 'pending' : 'skipped',
                    ]);

                    $this->line("  Signal: {$symbol} {$signal['direction']} score={$signal['score']}");
                }
            } catch (\Exception $e) {
                $this->error("  Error: " . $e->getMessage());
            }
        }

        $this->info('Signal generation complete.');
        return 0;
    }
}

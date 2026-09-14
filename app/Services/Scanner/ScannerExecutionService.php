<?php

namespace App\Services\Scanner;

use App\Models\BotPosition;
use App\Models\BotTrade;
use App\Models\ExchangeAccount;
use App\Models\ExchangeMarket;
use App\Models\ScannerConfig;
use App\Models\ScannerSignal;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\Log;

class ScannerExecutionService
{
    /**
     * Build an order plan with correct sizing / precision / min-notional.
     *
     * @return array{ok:bool, errors:array, plan:?array}
     */
    public function prepareOrder(ExchangeAccount $account, ExchangeMarket $market, ScannerSignal $signal, ScannerConfig $config, float $equity): array
    {
        $errors = [];
        $long = in_array($signal->direction, ['long', 'buy'], true);

        $entry = (float) $signal->entry_price;
        $stop = (float) $signal->stop_loss;
        if ($entry <= 0 || $stop <= 0 || ($long && $stop >= $entry) || (!$long && $stop <= $entry)) {
            $errors[] = 'Invalid entry/stop levels';
        }

        $stopDistancePct = $entry > 0 ? abs($entry - $stop) / $entry : 0;
        if ($stopDistancePct <= 0) {
            $errors[] = 'Stop distance is zero';
        }

        $riskAmount = $equity * ((float) $config->risk_per_trade / 100);
        $riskAmount = max($riskAmount, 0.01);

        $leverage = 1;
        if ($market->market_type === 'futures' && data_get($market, 'leverage_limit', 1) > 0) {
            $leverage = (int) min((int) $config->max_leverage, (int) $market->leverage_limit);
        }

        // Position size so that risk = stop distance of notional.
        $notional = $market->market_type === 'futures'
            ? ($riskAmount / $stopDistancePct) * $leverage
            : $riskAmount / $stopDistancePct;

        $quantity = $notional / $entry;

        $quantityPrecision = (int) (data_get($market, 'quantity_precision', 6));
        $pricePrecision = (int) (data_get($market, 'price_precision', 6));
        $qty = $this->truncatePrecision($quantity, $quantityPrecision);
        $qty = max($qty, (float) $market->min_quantity);

        $notionalActual = $qty * $entry;
        $minNotional = (float) $market->min_notional;
        if ($notionalActual < $minNotional) {
            $errors[] = sprintf('Notional %.4f below min %s', $notionalActual, rtrim(rtrim($minNotional, '0'), '.'));
        }

        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors, 'plan' => null];
        }

        return [
            'ok' => true,
            'errors' => [],
            'plan' => [
                'side' => $long ? 'buy' : 'sell',
                'quantity' => round($qty, $quantityPrecision),
                'entry_price' => round($entry, $pricePrecision),
                'stop_loss' => round($stop, $pricePrecision),
                'take_profit' => round((float) $signal->take_profit, $pricePrecision),
                'leverage' => $leverage,
                'risk_amount' => $riskAmount,
                'notional' => $notionalActual,
                'margin' => $market->market_type === 'futures' ? $notionalActual / $leverage : $notionalActual,
                'stop_distance_pct' => round($stopDistancePct * 100, 4),
            ],
        ];
    }

    public function equity(ExchangeAccount $account): float
    {
        try {
            $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());
            $balances = $adapter->getBalances();
        } catch (\Throwable $e) {
            Log::warning('scanner.execution.balance_failed', [
                'account' => $account->id, 'error' => $e->getMessage(),
            ]);
            return 0;
        }

        $usdt = ScannerRiskEngine::usdtBalance($balances);
        if ($usdt > 0) return $usdt;

        return ScannerRiskEngine::totalBalance($balances);
    }

    /**
     * Execute a validated scanner signal.
     * Simulates when paper mode; otherwise sends a real order to the exchange.
     *
     * @return array{ok:bool, message:string, trade:?BotTrade}
     */
    public function execute(ScannerSignal $signal, ExchangeAccount $account, ExchangeMarket $market, ScannerConfig $config): array
    {
        $equity = $this->equity($account);
        $order = $this->prepareOrder($account, $market, $signal, $config, $equity);
        if (!$order['ok']) {
            return ['ok' => false, 'message' => implode('; ', $order['errors']), 'trade' => null];
        }
        $plan = $order['plan'];

        $filledPrice = $plan['entry_price'];
        $exchangeOrderId = null;
        $status = 'simulated';

        if (!$config->paper_mode) {
            try {
                $adapter = ExchangeFactory::make($account->exchange, $account->getDecryptedCredentials());

                if ($market->market_type === 'futures' && $plan['leverage'] > 1) {
                    try {
                        $adapter->setLeverage($market->symbol, $plan['leverage']);
                    } catch (\Throwable $e) {
                        Log::debug('scanner.execution.leverage_failed', [
                            'symbol' => $market->symbol, 'error' => $e->getMessage(),
                        ]);
                    }
                }

                $result = $adapter->placeOrder([
                    'symbol' => $market->symbol,
                    'type' => 'market',
                    'side' => $plan['side'],
                    'quantity' => $plan['quantity'],
                    'stopLoss' => $plan['stop_loss'],
                    'takeProfit' => $plan['take_profit'],
                ]);

                $filledPrice = (float) ($result['price'] ?? $plan['entry_price']);
                $exchangeOrderId = $result['order_id'] ?? null;
                $status = 'open';
            } catch (\Throwable $e) {
                Log::error('scanner.execution.order_failed', [
                    'account' => $account->id, 'symbol' => $market->symbol,
                    'error' => $e->getMessage(),
                ]);
                return ['ok' => false, 'message' => 'Exchange rejected order: '.$e->getMessage(), 'trade' => null];
            }
        }

        $trade = new BotTrade();
        $trade->user_id = $account->user_id;
        $trade->exchange_account_id = $account->id;
        $trade->signal_id = $signal->id;
        $trade->source = 'scanner';
        $trade->bot_id = null;
        $trade->exchange = $account->exchange;
        $trade->market_type = $market->market_type;
        $trade->symbol = $market->symbol;
        $trade->side = $plan['side'];
        $trade->entry_price = $filledPrice;
        $trade->exit_price = null;
        $trade->quantity = $plan['quantity'];
        $trade->leverage = $plan['leverage'];
        $trade->margin = $plan['margin'];
        $trade->stop_loss = $plan['stop_loss'];
        $trade->take_profit = $plan['take_profit'];
        $trade->exchange_order_id = $exchangeOrderId;
        $trade->status = $status;
        $trade->opened_at = now();

        // Entry fees / slippage estimate (live fees are exchange-returned elsewhere).
        $entryNotional = abs($filledPrice * $plan['quantity']);
        $trade->fees = round($entryNotional * 0.001, 8);
        $trade->slippage = 0;
        $trade->metadata = ['source' => 'scanner', 'paper' => $config->paper_mode, 'signal_score' => $signal->signal_score];
        $trade->save();

        $position = new BotPosition();
        $position->user_id = $account->user_id;
        $position->exchange_account_id = $account->id;
        $position->bot_id = null;
        $position->signal_id = $signal->id;
        $position->exchange = $account->exchange;
        $position->market_type = $market->market_type;
        $position->symbol = $market->symbol;
        $position->side = $plan['side'];
        $position->quantity = $plan['quantity'];
        $position->entry_price = $filledPrice;
        $position->current_price = $filledPrice;
        $position->leverage = $plan['leverage'];
        $position->margin = $plan['margin'];

        // Estimate isolated liquidation price for futures positions.
        if ($market->market_type === 'futures' && $plan['leverage'] > 1) {
            $mmr = 0.005;
            $long = $plan['side'] === 'buy';
            $position->liquidation_price = $long
                ? $filledPrice * (1 - (1 / $plan['leverage']) + $mmr)
                : $filledPrice * (1 + (1 / $plan['leverage']) - $mmr);
        }

        $position->stop_loss = $plan['stop_loss'];
        $position->take_profit = $plan['take_profit'];
        $position->status = 'open';
        $position->opened_at = now();
        $position->save();

        $signal->status = 'executed';
        $signal->executed_at = now();
        $signal->save();

        return ['ok' => true, 'message' => 'Executed', 'trade' => $trade];
    }

    protected function truncatePrecision(float $value, int $precision): float
    {
        $factor = 10 ** $precision;
        return floor($value * $factor) / $factor;
    }
}
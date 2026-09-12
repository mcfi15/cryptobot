<?php

namespace App\Services\Scanner;

use App\Models\ExchangeMarket;
use App\Models\ScannerConfig;
use App\Models\ScannerWatchlistEntry;

class WatchlistService
{
    public function entries(int $configId): \Illuminate\Database\Eloquent\Collection
    {
        return ScannerWatchlistEntry::where('config_id', $configId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Validate the symbol against known exchanges and add it.
     */
    public function add(int $userId, int $configId, string $symbol, ?string $exchange = null, string $marketType = 'both'): array
    {
        $symbol = strtoupper(trim($symbol));

        $exists = ScannerWatchlistEntry::where('config_id', $configId)
            ->where('symbol', $symbol)
            ->exists();
        if ($exists) {
            return ['ok' => false, 'error' => 'Symbol already in watchlist'];
        }

        // Validate symbol exists on at least one connected exchange.
        $query = ExchangeMarket::where('symbol', $symbol);
        if ($exchange) $query->where('exchange', $exchange);
        if (!$query->exists()) {
            return ['ok' => false, 'error' => 'Symbol not found in exchange markets'];
        }

        $maxSort = (int) ScannerWatchlistEntry::where('config_id', $configId)->max('sort_order');

        ScannerWatchlistEntry::create([
            'user_id' => $userId,
            'config_id' => $configId,
            'symbol' => $symbol,
            'exchange' => $exchange,
            'market_type' => $marketType,
            'active' => true,
            'sort_order' => $maxSort + 1,
        ]);

        return ['ok' => true, 'error' => null];
    }

    public function remove(int $configId, int $entryId): array
    {
        $entry = ScannerWatchlistEntry::where('config_id', $configId)->find($entryId);
        if (!$entry) {
            return ['ok' => false, 'error' => 'Entry not found'];
        }
        $entry->delete();
        $this->renumber($configId);
        return ['ok' => true, 'error' => null];
    }

    public function setActive(int $configId, int $entryId, bool $active): array
    {
        $entry = ScannerWatchlistEntry::where('config_id', $configId)->find($entryId);
        if (!$entry) {
            return ['ok' => false, 'error' => 'Entry not found'];
        }
        $entry->active = $active;
        $entry->save();
        return ['ok' => true, 'error' => null];
    }

    public function reorder(int $configId, array $order): void
    {
        foreach ($order as $position => $entryId) {
            ScannerWatchlistEntry::where('config_id', $configId)
                ->where('id', $entryId)
                ->update(['sort_order' => (int) $position]);
        }
    }

    public function activeSymbols(int $configId): array
    {
        return ScannerWatchlistEntry::where('config_id', $configId)
            ->where('active', true)
            ->pluck('symbol')
            ->all();
    }

    protected function renumber(int $configId): void
    {
        $i = 0;
        foreach (ScannerWatchlistEntry::where('config_id', $configId)->orderBy('sort_order')->get() as $entry) {
            $entry->update(['sort_order' => $i++]);
        }
    }
}
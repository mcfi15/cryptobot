<?php

namespace App\Services\Scanner;

use App\Models\ScannerActivity;

/**
 * Lightweight user-facing activity feed logger for the scanner pipeline.
 * Levels: info | warning | success | danger.
 */
class ActivityLogger
{
    public static function log(
        int $userId,
        string $event,
        string $message,
        string $level = 'info',
        ?int $configId = null,
        ?int $signalId = null,
        array $meta = []
    ): ScannerActivity {
        $activity = new ScannerActivity();
        $activity->user_id = $userId;
        $activity->config_id = $configId;
        $activity->signal_id = $signalId;
        $activity->level = $level;
        $activity->event = $event;
        $activity->message = $message;
        $activity->meta = $meta ?: null;
        $activity->save();

        return $activity;
    }

    public static function recent(int $userId, int $limit = 30)
    {
        return ScannerActivity::where('user_id', $userId)
            ->latest()
            ->limit(max(1, min(200, $limit)))
            ->get();
    }
}
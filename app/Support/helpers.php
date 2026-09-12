<?php

use App\Models\GlobalSetting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('site_setting')) {
    function site_setting(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember('site_setting:' . $key, 3600, fn () => GlobalSetting::get($key));

        return $value === null ? $default : $value;
    }
}
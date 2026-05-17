<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    $color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));

    Cache::put('scheduler_showcase', [
        'color' => $color,
        'last_run' => now()->toTimeString(),
        'count' => Cache::get('scheduler_showcase.count', 0) + 1,
    ]);
})->everySecond();

<?php

declare(strict_types=1);

use App\Http\Controllers\Showcase\ResetQueueDemoController;
use App\Http\Controllers\Showcase\RunPogoDemoController;
use App\Http\Controllers\Showcase\RunQueueDemoController;
use App\Http\Controllers\Showcase\SendMessageController;
use App\Http\Controllers\Showcase\ShowPogoController;
use App\Http\Controllers\Showcase\ShowQueueController;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'nossr'])->group(function (): void {
    Route::get('/chat', fn () => Inertia::render('showcase/Chat'))->name('showcase.chat');
    Route::post('/chat/message', SendMessageController::class)->name('showcase.chat.message');

    Route::get('/pogo', ShowPogoController::class)->name('showcase.pogo');
    Route::post('/pogo/run', RunPogoDemoController::class)->name('showcase.pogo.run');

    Route::get('/queue', ShowQueueController::class)->name('showcase.queue');
    Route::post('/queue/run', RunQueueDemoController::class)->name('showcase.queue.run');
    Route::post('/queue/reset', ResetQueueDemoController::class)->name('showcase.queue.reset');
});

Route::get('/showcase', function () {
    return Inertia::render('showcase/Scheduler', [
        'schedulerData' => Cache::get('scheduler_showcase', [
            'color' => '#gray',
            'last_run' => 'Waiting...',
            'count' => 0,
        ]),
    ]);
})->name('showcase.scheduler');

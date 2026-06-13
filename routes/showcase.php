<?php

declare(strict_types=1);

use App\Http\Controllers\Showcase\CreateUploadIntentController;
use App\Http\Controllers\Showcase\PingUploadPressureController;
use App\Http\Controllers\Showcase\ResetQueueDemoController;
use App\Http\Controllers\Showcase\RunPogoDemoController;
use App\Http\Controllers\Showcase\RunQueueDemoController;
use App\Http\Controllers\Showcase\RunRawUploadController;
use App\Http\Controllers\Showcase\SendMessageController;
use App\Http\Controllers\Showcase\ShowPogoController;
use App\Http\Controllers\Showcase\ShowQueueController;
use App\Http\Controllers\Showcase\ShowUploadController;
use App\Http\Controllers\Showcase\ShowUploadProgressController;
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

    Route::get('/upload', ShowUploadController::class)->name('showcase.upload');
    Route::post('/upload/raw', RunRawUploadController::class)->name('showcase.upload.raw');
    Route::get('/upload/ping', PingUploadPressureController::class)->name('showcase.upload.ping');
    Route::post('/upload/pogo/intent', CreateUploadIntentController::class)->name('showcase.upload.intent');
    Route::get('/upload/pogo/{uploadId}', ShowUploadProgressController::class)
        ->where('uploadId', '[A-Za-z0-9_]+')
        ->name('showcase.upload.progress');
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

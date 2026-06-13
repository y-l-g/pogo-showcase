<?php

declare(strict_types=1);

use App\Http\Controllers\Public\LandingParallelController;
use App\Http\Controllers\Public\LandingPulseController;
use App\Http\Controllers\Public\SendLandingChatMessageController;
use App\Http\Controllers\Public\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');
Route::post('/examples/chat/message', SendLandingChatMessageController::class)
    ->middleware('throttle:20,1')
    ->name('examples.chat.message');
Route::get('/examples/pulse', LandingPulseController::class)->name('examples.pulse');
Route::post('/examples/parallel', LandingParallelController::class)->name('examples.parallel');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/showcase.php';

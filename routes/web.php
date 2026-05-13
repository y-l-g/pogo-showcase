<?php

declare(strict_types=1);

use App\Http\Controllers\Public\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/showcase.php';



<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WelcomeController
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('public/Welcome');
    }
}

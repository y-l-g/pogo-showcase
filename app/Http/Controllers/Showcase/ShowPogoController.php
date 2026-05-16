<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\PogoShowcase\PogoDispatcher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ShowPogoController extends Controller
{
    public function __invoke(Request $request, PogoDispatcher $pogo): Response
    {
        return Inertia::render('showcase/Pogo', [
            'pogoAvailable' => $pogo->available(),
            'poolSizes' => [
                'default' => $pogo->poolSize('default'),
                'external_api' => $pogo->poolSize('external_api'),
            ],
            'demoResults' => $request->session()->get('pogo_demo_results', []),
        ]);
    }
}

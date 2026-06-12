<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Http\JsonResponse;

final readonly class LandingPulseController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ran_at' => now()->format('H:i:s'),
            'server_second' => (int) now()->format('s'),
        ]);
    }
}

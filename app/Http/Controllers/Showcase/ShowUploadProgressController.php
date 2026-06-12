<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\UploadShowcase\UploadShowcase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowUploadProgressController extends Controller
{
    public function __invoke(Request $request, string $uploadId, UploadShowcase $uploads): JsonResponse
    {
        if (! $request->user()) {
            abort(403);
        }

        return response()->json([
            'progress' => $uploads->progress($uploadId),
            'event' => $uploads->event($request->user(), $uploadId),
            'status' => $uploads->status(),
        ]);
    }
}

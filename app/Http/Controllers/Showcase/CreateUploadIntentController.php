<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\UploadShowcase\UploadShowcase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

final class CreateUploadIntentController extends Controller
{
    public function __invoke(Request $request, UploadShowcase $uploads): JsonResponse
    {
        $startedAt = hrtime(true);

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:160'],
            'content_type' => ['required', 'string', Rule::in(UploadShowcase::acceptedContentTypes())],
            'size' => ['required', 'integer', 'min:1', 'max:'.UploadShowcase::MAX_BYTES],
        ]);

        if (! $request->user()) {
            abort(403);
        }

        try {
            $intent = $uploads->createIntent(
                $request->user(),
                (string) $validated['filename'],
                (string) $validated['content_type'],
                (int) $validated['size'],
            );

            $intent['php_elapsed_ms'] = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            return response()->json($intent);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => 'Unable to create a Pogo upload intent.',
            ], 500);
        }
    }
}

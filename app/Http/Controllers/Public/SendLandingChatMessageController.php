<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Events\LandingChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final readonly class SendLandingChatMessageController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'content' => ['required', 'string', 'min:1', 'max:180'],
        ]);

        $message = [
            'id' => (string) Str::uuid(),
            'name' => trim((string) $validated['name']),
            'content' => trim((string) $validated['content']),
            'timestamp' => now()->toIso8601String(),
        ];

        $broadcasted = true;

        try {
            event(new LandingChatMessage($message));
        } catch (Throwable) {
            $broadcasted = false;
        }

        return response()->json([
            'message' => $message,
            'broadcasted' => $broadcasted,
        ]);
    }
}

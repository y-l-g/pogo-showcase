<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Events\LandingChatMessage;
use App\Services\LandingChat\LandingChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

final readonly class SendLandingChatMessageController
{
    public function __invoke(Request $request, LandingChatRoom $chat): JsonResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'content' => trim((string) $request->input('content', '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
            'content' => ['required', 'string', 'min:1', 'max:180'],
        ]);

        $message = [
            'id' => (string) Str::uuid(),
            'name' => (string) $validated['name'],
            'content' => (string) $validated['content'],
            'timestamp' => now()->toIso8601String(),
        ];

        $chat->record($message);

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

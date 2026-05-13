<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Events\ChatMessage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

final class SendMessageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
            'type' => ['required', 'string', 'in:public,private,presence'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        ChatMessage::dispatch(
            $user,
            (string) $validated['content'],
            (string) $validated['type']
        );

        return redirect()->back();
    }
}
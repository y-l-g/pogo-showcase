<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Events\ChatMessage;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SendMessageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
            'type' => ['required', 'string', 'in:public,private,presence'],
        ]);

        /** @var User $user */
        $user = $request->user();

        event(new ChatMessage($user, (string) $validated['content'], (string) $validated['type']));

        return back();
    }
}

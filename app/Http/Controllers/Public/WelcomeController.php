<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\LandingChat\LandingChatRoom;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WelcomeController
{
    public function __invoke(LandingChatRoom $chat): Response
    {
        return Inertia::render('public/Welcome', [
            'landingChatMessages' => $chat->messages(),
        ]);
    }
}

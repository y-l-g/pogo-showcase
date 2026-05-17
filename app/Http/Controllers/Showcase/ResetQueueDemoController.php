<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\QueueShowcase\QueueBoard;
use Illuminate\Http\RedirectResponse;

final class ResetQueueDemoController extends Controller
{
    public function __invoke(QueueBoard $board): RedirectResponse
    {
        $board->reset();

        return to_route('showcase.queue')
            ->with('success', 'Queue board reset.');
    }
}

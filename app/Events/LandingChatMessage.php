<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class LandingChatMessage implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{id: string, name: string, content: string, timestamp: string}  $message
     */
    public function __construct(public readonly array $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('landing.chat');
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array{id: string, name: string, content: string, timestamp: string}
     */
    public function broadcastWith(): array
    {
        return $this->message;
    }
}

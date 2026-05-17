<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.private', fn (User $user): bool => true);

Broadcast::channel('chat.presence', fn (User $user): array => [
    'id' => $user->id,
    'name' => $user->name,
    'avatar' => $user->avatar ?? null,
]);

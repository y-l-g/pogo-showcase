<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.private', fn (?User $user): bool => $user !== null);

Broadcast::channel('chat.presence', fn (?User $user): array|bool => $user === null ? false : [
    'id' => $user->id,
    'name' => $user->name,
    'avatar' => $user->avatar ?? null,
]);

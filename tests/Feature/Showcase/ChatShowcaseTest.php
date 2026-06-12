<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('authorizes private chat channels with a socket-aware pusher signature', function (): void {
    $user = User::factory()->create();
    $appId = (string) config('broadcasting.connections.pogo.app_id');
    $secret = (string) config('broadcasting.connections.pogo.secret');

    $response = actingAs($user)->postJson('/pogo/auth', [
        'socket_id' => '1.1',
        'channel_name' => 'private-chat.private',
    ]);

    $response
        ->assertOk()
        ->assertJson([
            'auth' => $appId.':'.hash_hmac('sha256', '1.1:private-chat.private', $secret),
        ]);
});

it('authorizes presence chat channels with channel data in the pusher signature', function (): void {
    $user = User::factory()->create([
        'name' => 'Presence User',
    ]);
    $appId = (string) config('broadcasting.connections.pogo.app_id');
    $secret = (string) config('broadcasting.connections.pogo.secret');

    $response = actingAs($user)->postJson('/pogo/auth', [
        'socket_id' => '1.1',
        'channel_name' => 'presence-chat.presence',
    ]);

    $channelData = json_encode([
        'user_id' => (string) $user->id,
        'user_info' => [
            'id' => $user->id,
            'name' => 'Presence User',
            'avatar' => null,
        ],
    ]);

    expect($channelData)->toBeString();

    $response
        ->assertOk()
        ->assertJson([
            'auth' => $appId.':'.hash_hmac('sha256', '1.1:presence-chat.presence:'.$channelData, $secret),
            'channel_data' => $channelData,
        ]);
});

it('denies unauthenticated private chat channel authorization', function (): void {
    $response = $this->postJson('/pogo/auth', [
        'socket_id' => '1.1',
        'channel_name' => 'private-chat.private',
    ]);

    $response->assertForbidden();
});

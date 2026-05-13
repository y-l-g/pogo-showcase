<?php

declare(strict_types=1);

use App\Models\User;

test('correct password must be provided before an account can be deleted', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->delete('settings/profile', [
        'password' => 'wrong-password',
    ]);

    expect($user->fresh())->not->toBeNull();
});

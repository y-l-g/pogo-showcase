<?php

declare(strict_types=1);

use App\Events\LandingChatMessage;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the landing page with public examples', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('public/Welcome')
        );
});

it('accepts a public landing chat message', function (): void {
    Event::fake([LandingChatMessage::class]);

    $this->postJson(route('examples.chat.message'), [
        'name' => 'Visitor',
        'content' => 'Hello from the test',
    ])
        ->assertOk()
        ->assertJsonPath('message.name', 'Visitor')
        ->assertJsonPath('message.content', 'Hello from the test')
        ->assertJsonPath('broadcasted', true);

    Event::assertDispatched(
        LandingChatMessage::class,
        fn (LandingChatMessage $event): bool => $event->message['content'] === 'Hello from the test'
    );
});

it('returns a public pulse payload', function (): void {
    $this->getJson(route('examples.pulse'))
        ->assertOk()
        ->assertJsonStructure([
            'ran_at',
            'server_second',
        ]);
});

it('runs the public parallel fallback without pogo', function (): void {
    $this->postJson(route('examples.parallel'))
        ->assertOk()
        ->assertJsonPath('mode', 'php_fallback')
        ->assertJsonCount(3, 'jobs')
        ->assertJsonStructure([
            'mode',
            'elapsed_ms',
            'jobs' => [
                '*' => ['label', 'duration_ms'],
            ],
        ]);
});

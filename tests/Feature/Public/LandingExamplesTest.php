<?php

declare(strict_types=1);

use App\Events\LandingChatMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Cache::flush();
});

it('renders the landing page with public examples', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('public/Welcome')
                ->where('landingChatMessages', [])
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

it('rejects blank public landing chat messages after trimming', function (): void {
    Event::fake([LandingChatMessage::class]);

    $this->postJson(route('examples.chat.message'), [
        'name' => '  ',
        'content' => '   ',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'content']);

    Event::assertNotDispatched(LandingChatMessage::class);
});

it('hydrates the landing page with recent public chat messages', function (): void {
    Event::fake([LandingChatMessage::class]);

    $this->postJson(route('examples.chat.message'), [
        'name' => 'Visitor',
        'content' => 'Hello from hydration',
    ])->assertOk();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('public/Welcome')
                ->has('landingChatMessages', 1)
                ->where('landingChatMessages.0.name', 'Visitor')
                ->where('landingChatMessages.0.content', 'Hello from hydration')
        );
});

it('keeps only the recent public chat messages', function (): void {
    Event::fake([LandingChatMessage::class]);

    foreach (range(1, 14) as $index) {
        $this->postJson(route('examples.chat.message'), [
            'name' => 'Visitor',
            'content' => "Message {$index}",
        ])->assertOk();
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('public/Welcome')
                ->has('landingChatMessages', 12)
                ->where('landingChatMessages.0.content', 'Message 3')
                ->where('landingChatMessages.11.content', 'Message 14')
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

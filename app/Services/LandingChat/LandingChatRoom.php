<?php

declare(strict_types=1);

namespace App\Services\LandingChat;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final readonly class LandingChatRoom
{
    private const CACHE_KEY = 'landing-chat.messages';

    private const LOCK_KEY = 'landing-chat.messages.lock';

    private const HISTORY_LIMIT = 12;

    private const CACHE_TTL_SECONDS = 86_400;

    /**
     * @return list<array{id: string, name: string, content: string, timestamp: string}>
     */
    public function messages(): array
    {
        return $this->normalizeMessages(Cache::get(self::CACHE_KEY));
    }

    /**
     * @param  array{id: string, name: string, content: string, timestamp: string}  $message
     */
    public function record(array $message): void
    {
        try {
            Cache::lock(self::LOCK_KEY, 5)->block(
                1,
                fn () => $this->storeMessage($message)
            );
        } catch (LockTimeoutException) {
            $this->storeMessage($message);
        }
    }

    /**
     * @param  array{id: string, name: string, content: string, timestamp: string}  $message
     */
    private function storeMessage(array $message): void
    {
        $messages = [...$this->messages(), $message];

        Cache::put(
            self::CACHE_KEY,
            array_slice($messages, -self::HISTORY_LIMIT),
            self::CACHE_TTL_SECONDS
        );
    }

    /**
     * @return list<array{id: string, name: string, content: string, timestamp: string}>
     */
    private function normalizeMessages(mixed $messages): array
    {
        if (! is_array($messages)) {
            return [];
        }

        $normalized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $id = $message['id'] ?? null;
            $name = $message['name'] ?? null;
            $content = $message['content'] ?? null;
            $timestamp = $message['timestamp'] ?? null;

            if (! is_string($id) || ! is_string($name) || ! is_string($content) || ! is_string($timestamp)) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'content' => $content,
                'timestamp' => $timestamp,
            ];
        }

        return array_slice($normalized, -self::HISTORY_LIMIT);
    }
}

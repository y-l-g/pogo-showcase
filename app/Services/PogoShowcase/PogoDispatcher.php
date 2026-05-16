<?php

declare(strict_types=1);

namespace App\Services\PogoShowcase;

class PogoDispatcher
{
    public function available(): bool
    {
        return function_exists('pogo_dispatch')
            && function_exists('pogo_await')
            && function_exists('pogo_pool_size');
    }

    public function poolSize(string $pool = 'default'): int
    {
        if (! function_exists('pogo_pool_size')) {
            return 0;
        }

        return (int) pogo_pool_size($pool);
    }

    /**
     * @param array<string, mixed> $args
     */
    public function dispatch(string $class, array $args = [], string $pool = 'default'): int
    {
        return (int) pogo_dispatch($class, $args, $pool);
    }

    public function await(int $handle, float $timeout = 5.0): mixed
    {
        return pogo_await($handle, $timeout);
    }
}

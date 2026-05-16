<?php

declare(strict_types=1);

namespace App\Jobs\Pogo;

use Pogo\JobInterface;

final class FetchPriceJob implements JobInterface
{
    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $sku = strtoupper((string) ($args['sku'] ?? 'POGO-001'));
        $quantity = max(1, (int) ($args['quantity'] ?? 1));
        $delayMs = max(0, (int) ($args['delay_ms'] ?? 0));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $base = 80 + (crc32($sku) % 7000) / 100;
        $discount = $quantity >= 5 ? 0.9 : 1.0;

        return [
            'source' => 'pricing',
            'sku' => $sku,
            'unit_price' => round($base * $discount, 2),
            'currency' => 'EUR',
            'delay_ms' => $delayMs,
        ];
    }
}

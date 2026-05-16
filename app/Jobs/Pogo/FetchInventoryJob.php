<?php

declare(strict_types=1);

namespace App\Jobs\Pogo;

use Pogo\JobInterface;

final class FetchInventoryJob implements JobInterface
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

        $available = 20 + (crc32('stock-'.$sku) % 180);

        return [
            'source' => 'inventory',
            'sku' => $sku,
            'requested' => $quantity,
            'available' => $available,
            'can_fulfill' => $available >= $quantity,
            'delay_ms' => $delayMs,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs\Pogo;

use Pogo\JobInterface;

final class FetchDeliveryWindowJob implements JobInterface
{
    /**
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
     */
    public function handle(array $args): array
    {
        $sku = strtoupper((string) ($args['sku'] ?? 'POGO-001'));
        $delayMs = max(0, (int) ($args['delay_ms'] ?? 0));

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        $days = 1 + (crc32('delivery-'.$sku) % 5);

        return [
            'source' => 'delivery',
            'sku' => $sku,
            'carrier' => $days <= 2 ? 'Express' : 'Standard',
            'eta_days' => $days,
            'window' => (new \DateTimeImmutable())->modify("+{$days} days")->format('M d'),
            'delay_ms' => $delayMs,
        ];
    }
}

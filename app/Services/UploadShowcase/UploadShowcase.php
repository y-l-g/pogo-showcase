<?php

declare(strict_types=1);

namespace App\Services\UploadShowcase;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class UploadShowcase
{
    public const int MAX_BYTES = 52_428_800;

    /**
     * @return list<string>
     */
    public static function acceptedContentTypes(): array
    {
        return [
            'application/octet-stream',
            'text/plain',
            'image/jpeg',
            'image/png',
            'application/pdf',
        ];
    }

    public function available(): bool
    {
        return \function_exists('pogo_upload_create')
            && \function_exists('pogo_upload_progress')
            && \function_exists('pogo_upload_status');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $defaults = [
            'ready' => false,
            'store' => 'default',
            'active_uploads' => 0,
            'accepted' => 0,
            'completed' => 0,
            'failed' => 0,
            'size_limit_failures' => 0,
            'content_type_failures' => 0,
            'bytes_received' => 0,
            'max_upload_bytes' => self::MAX_BYTES,
            'worker_event_failures' => 0,
        ];

        if (! \function_exists('pogo_upload_status')) {
            return $defaults;
        }

        try {
            $decoded = json_decode((string) \pogo_upload_status('default'), true);
        } catch (Throwable) {
            return $defaults;
        }

        if (! is_array($decoded)) {
            return $defaults;
        }

        $store = $decoded['stores'][0] ?? [];
        if (! is_array($store)) {
            $store = [];
        }

        return array_merge($defaults, $store, [
            'ready' => ($decoded['ready'] ?? false) === true && ($store['ready'] ?? false) === true,
            'store' => (string) ($store['store'] ?? 'default'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createIntent(User $user, string $filename, string $contentType, int $size): array
    {
        if (! \function_exists('pogo_upload_create')) {
            throw new RuntimeException('The Pogo upload extension is not loaded in this PHP runtime.');
        }

        $safeFilename = $this->safeFilename($filename);
        $key = implode('/', [
            'showcase',
            'users',
            (string) $user->id,
            Str::uuid()->toString(),
            $safeFilename,
        ]);

        return \pogo_upload_create([
            'key' => $key,
            'filename' => $safeFilename,
            'content_types' => [$contentType],
            'max_bytes' => min($size, self::MAX_BYTES),
            'overwrite' => false,
            'expires_in' => 600,
            'metadata' => [
                'user_id' => (string) $user->id,
                'purpose' => 'showcase',
                'mode' => 'pogo',
            ],
        ], 'default');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function progress(string $uploadId): ?array
    {
        if (! \function_exists('pogo_upload_progress')) {
            return null;
        }

        try {
            $decoded = \pogo_upload_progress($uploadId, 'default');
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function event(User $user, string $uploadId): ?array
    {
        $event = Cache::get(self::eventCacheKey((string) $user->id, $uploadId));

        return is_array($event) ? $event : null;
    }

    public static function eventCacheKey(string $userId, string $uploadId): string
    {
        return "upload-showcase:event:{$userId}:{$uploadId}";
    }

    private function safeFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $basename), '.-');

        return $name !== '' ? Str::limit($name, 120, '') : 'upload.bin';
    }
}

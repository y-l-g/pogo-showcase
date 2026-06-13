<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\UploadShowcase\UploadShowcase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RunRawUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $request->user()) {
            abort(403);
        }

        $filename = $this->safeFilename((string) $request->headers->get('X-Upload-Filename', 'upload.bin'));
        $contentType = (string) $request->headers->get('Content-Type', 'application/octet-stream');
        $contentLength = (int) $request->headers->get('Content-Length', '0');
        $pressureDelayUs = $this->pressureDelayUs($request);

        if (! in_array($contentType, UploadShowcase::acceptedContentTypes(), true)) {
            return $this->failure('unsupported_content_type', 'Content type is not accepted.', 415);
        }

        if ($contentLength > UploadShowcase::MAX_BYTES) {
            return $this->failure('too_large', 'Upload exceeds the raw PHP demo limit.', 413);
        }

        $startedAt = hrtime(true);
        $root = storage_path('app/upload-showcase/raw');
        $key = implode('/', [
            'users',
            (string) $request->user()->id,
            Str::uuid()->toString(),
            $filename,
        ]);
        $target = $root.'/'.$key;
        $tmp = $target.'.part';

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0750, true);
        }

        $input = $request->getContent(true);
        $output = fopen($tmp, 'wb');
        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            while (! feof($input)) {
                $chunk = fread($input, 65536);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $bytes += strlen($chunk);
                if ($bytes > UploadShowcase::MAX_BYTES) {
                    @unlink($tmp);

                    return $this->failure('too_large', 'Upload exceeds the raw PHP demo limit.', 413);
                }

                hash_update($hash, $chunk);
                fwrite($output, $chunk);

                if ($pressureDelayUs > 0) {
                    usleep($pressureDelayUs);
                }
            }
        } finally {
            fclose($input);
            fclose($output);
        }

        rename($tmp, $target);

        $elapsedMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return response()->json([
            'ok' => true,
            'mode' => 'raw_php',
            'filename' => $filename,
            'key' => $key,
            'content_type' => $contentType,
            'bytes' => $bytes,
            'sha256' => hash_final($hash),
            'elapsed_ms' => $elapsedMs,
            'php_elapsed_ms' => $elapsedMs,
            'php_handled_body' => true,
        ]);
    }

    private function failure(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    private function safeFilename(string $filename): string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $basename), '.-');

        return $name !== '' ? Str::limit($name, 120, '') : 'upload.bin';
    }

    private function pressureDelayUs(Request $request): int
    {
        $delayMs = (int) $request->headers->get('X-Upload-Pressure-Delay-Ms', '0');

        return min(max($delayMs, 0), 250) * 1000;
    }
}

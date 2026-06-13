<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\UploadShowcase\UploadShowcase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the upload showcase page with normalized native status', function (): void {
    $GLOBALS['pogo_upload_status_payload'] = [
        'ready' => true,
        'stores' => [[
            'store' => 'default',
            'ready' => true,
            'active_uploads' => 1,
            'accepted' => 3,
            'completed' => 2,
            'failed' => 1,
            'bytes_received' => 4096,
            'max_upload_bytes' => 52428800,
        ]],
    ];

    $user = User::factory()->create();

    actingAs($user)
        ->get(route('showcase.upload'))
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('showcase/Upload', false)
                ->where('uploadAvailable', true)
                ->where('uploadStatus.ready', true)
                ->where('uploadStatus.active_uploads', 1)
                ->where('uploadStatus.completed', 2)
                ->where('maxBytes', UploadShowcase::MAX_BYTES)
                ->has('acceptedContentTypes')
        );
});

it('creates a signed pogo upload intent for the authenticated user', function (): void {
    $GLOBALS['pogo_upload_create_calls'] = [];
    $user = User::factory()->create();

    actingAs($user)
        ->postJson(route('showcase.upload.intent'), [
            'filename' => '../demo file.txt',
            'content_type' => 'text/plain',
            'size' => 11,
        ])
        ->assertOk()
        ->assertJsonPath('upload_id', 'upl_test')
        ->assertJsonPath('method', 'PUT')
        ->assertJsonPath('url', '/_pogo/upload/test-token')
        ->assertJsonStructure(['php_elapsed_ms']);

    expect($GLOBALS['pogo_upload_create_calls'])->toHaveCount(1);

    $call = $GLOBALS['pogo_upload_create_calls'][0];

    expect($call['store'])->toBe('default')
        ->and($call['intent']['filename'])->toBe('demo-file.txt')
        ->and($call['intent']['content_types'])->toBe(['text/plain'])
        ->and($call['intent']['metadata']['user_id'])->toBe((string) $user->id)
        ->and($call['intent']['metadata']['mode'])->toBe('pogo');
});

it('renders the upload showcase when native status is not configured', function (): void {
    $GLOBALS['pogo_upload_status_payload'] = 'throw';
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('showcase.upload'))
        ->assertInertia(
            fn (Assert $page): Assert => $page
                ->component('showcase/Upload', false)
                ->where('uploadAvailable', false)
                ->where('uploadStatus.ready', false)
        );
});

it('returns raw php upload results after streaming the request body', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->call(
        'POST',
        route('showcase.upload.raw'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'text/plain',
            'HTTP_X_UPLOAD_FILENAME' => 'raw demo.txt',
        ],
        'hello upload'
    );

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('mode', 'raw_php')
        ->assertJsonPath('filename', 'raw-demo.txt')
        ->assertJsonPath('bytes', 12)
        ->assertJsonPath('sha256', hash('sha256', 'hello upload'))
        ->assertJsonPath('php_handled_body', true)
        ->assertJsonStructure(['php_elapsed_ms']);

    $payload = $response->json();

    expect(file_exists(storage_path('app/upload-showcase/raw/'.$payload['key'])))->toBeTrue();
});

it('returns an authenticated upload pressure ping', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->getJson(route('showcase.upload.ping'))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonStructure(['checked_at']);
});

it('polls pogo progress and the cached completion event', function (): void {
    $user = User::factory()->create();
    $GLOBALS['pogo_upload_progress_payload'] = [
        'upload_id' => 'upl_test',
        'state' => 'receiving',
        'bytes_received' => 5,
        'max_bytes' => 12,
    ];

    Cache::put(UploadShowcase::eventCacheKey((string) $user->id, 'upl_test'), [
        'type' => 'completed',
        'upload_id' => 'upl_test',
        'bytes' => 12,
        'sha256' => hash('sha256', 'hello upload'),
    ]);

    actingAs($user)
        ->getJson(route('showcase.upload.progress', ['uploadId' => 'upl_test']))
        ->assertOk()
        ->assertJsonPath('progress.state', 'receiving')
        ->assertJsonPath('progress.bytes_received', 5)
        ->assertJsonPath('event.type', 'completed')
        ->assertJsonPath('event.bytes', 12);
});

it('rejects raw uploads with unsupported content types', function (): void {
    $user = User::factory()->create();

    actingAs($user)->call(
        'POST',
        route('showcase.upload.raw'),
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/x-msdownload',
            'HTTP_X_UPLOAD_FILENAME' => 'bad.exe',
        ],
        'payload'
    )
        ->assertStatus(415)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error.code', 'unsupported_content_type');
});

afterEach(function (): void {
    unset($GLOBALS['pogo_upload_create_calls']);
    unset($GLOBALS['pogo_upload_progress_payload']);
    unset($GLOBALS['pogo_upload_status_payload']);
});

if (! function_exists('pogo_upload_create')) {
    function pogo_upload_create(array $intent, string $store = 'default'): array
    {
        $GLOBALS['pogo_upload_create_calls'][] = [
            'intent' => $intent,
            'store' => $store,
        ];

        return [
            'upload_id' => 'upl_test',
            'method' => 'PUT',
            'url' => '/_pogo/upload/test-token',
            'headers' => [
                'content-type' => $intent['content_types'][0] ?? 'application/octet-stream',
            ],
            'expires_at' => '2026-06-12T12:00:00Z',
            'max_bytes' => $intent['max_bytes'],
        ];
    }
}

if (! function_exists('pogo_upload_progress')) {
    function pogo_upload_progress(string $uploadId, string $store = 'default'): ?array
    {
        return $GLOBALS['pogo_upload_progress_payload'] ?? null;
    }
}

if (! function_exists('pogo_upload_status')) {
    function pogo_upload_status(?string $store = null): string
    {
        if (($GLOBALS['pogo_upload_status_payload'] ?? null) === 'throw') {
            throw new RuntimeException('pogo_upload is not configured');
        }

        return json_encode($GLOBALS['pogo_upload_status_payload'] ?? [
            'ready' => false,
            'stores' => [[
                'store' => $store ?? 'default',
                'ready' => false,
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}

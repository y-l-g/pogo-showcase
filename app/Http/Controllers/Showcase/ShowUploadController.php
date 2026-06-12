<?php

declare(strict_types=1);

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Services\UploadShowcase\UploadShowcase;
use Inertia\Inertia;
use Inertia\Response;

final class ShowUploadController extends Controller
{
    public function __invoke(UploadShowcase $uploads): Response
    {
        $status = $uploads->status();

        return Inertia::render('showcase/Upload', [
            'uploadAvailable' => $uploads->available() && ($status['ready'] ?? false) === true,
            'uploadStatus' => $status,
            'maxBytes' => UploadShowcase::MAX_BYTES,
            'acceptedContentTypes' => UploadShowcase::acceptedContentTypes(),
        ]);
    }
}

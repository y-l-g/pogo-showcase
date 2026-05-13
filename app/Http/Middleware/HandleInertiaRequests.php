<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\Auth\UserData;
use App\Data\Inertia\AppPagePropsData;
use App\Data\Inertia\FlashData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();


        return [
            ...parent::share($request),
            ...AppPagePropsData::from([
                'user' => $user ? UserData::from($user) : null,
                'flash' => FlashData::from([
                    'success' => $request->session()->pull('success'),
                    'error' => $request->session()->pull('error'),
                    'info' => $request->session()->pull('info'),
                    'status' => $request->session()->pull('status'),
                ]),
            ])->toArray(),
        ];
    }
}

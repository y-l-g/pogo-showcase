<?php

declare(strict_types=1);

namespace App\Data\Inertia;

use App\Data\Auth\UserData;
use Spatie\LaravelData\Attributes\AutoClosureLazy;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AppPagePropsData extends Data
{
    public function __construct(
        #[AutoClosureLazy()]
        public readonly Lazy|null|UserData $user,
        public readonly FlashData $flash
    ) {
    }
}

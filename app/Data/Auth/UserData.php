<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Models\User;
use DateTime;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class UserData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly DateTime $createdAt,
        #[WithCast(DateTimeInterfaceCast::class)]
        public readonly ?DateTime $emailVerifiedAt,
        public readonly ?string $neutralColor,
        public readonly ?string $primaryColor,
        public readonly ?string $secondaryColor,
        public bool $hasPassword,
    ) {
    }

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->created_at,
            emailVerifiedAt: $user->email_verified_at,
            neutralColor: $user->neutral_color,
            primaryColor: $user->primary_color,
            secondaryColor: $user->secondary_color,
            hasPassword: $user->hasPassword(),
        );
    }
}

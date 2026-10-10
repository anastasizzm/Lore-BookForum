<?php
declare(strict_types=1);

namespace App\Forms\Auth;

use App\Forms\Form;

final readonly class PassResetForm implements Form
{
    public function __construct(
        public string $token,
        public string $password,
    ) {}

    public static function fromInput(array $input): self
    {
        return new self(
            token: (string) ($input['token'] ?? ''),
            password: (string) ($input['password'] ?? ''),
        );
    }
}
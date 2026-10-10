<?php
declare(strict_types=1);

namespace App\Forms\Auth;

use App\Forms\Form;

final readonly class AccountCreditsForm implements Form
{
    public function __construct(
        public string $username,
        public string $email,
    ){}

    public static function fromInput(array $input): self
    {
        return new self(
            username: trim($input['username'] ?? ''),
            email: mb_strtolower(trim($input['email'] ?? '')),
        );
    }
}
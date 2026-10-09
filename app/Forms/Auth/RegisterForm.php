<?php
declare(strict_types=1);

namespace App\Forms\Auth;

use App\Forms\Form;

final readonly class RegisterForm implements Form
{
    public function __construct(
        public string $username,
        public string $email,
        public string $name,
        public string $surname,
        public string $password,
    ) {}

    public static function fromInput(array $input): self
    {
        return new self(
            username: trim((string) ($input['username'] ?? '')),
            email:    mb_strtolower(trim((string) ($input['email'] ?? ''))),
            name:     trim((string) ($input['name'] ?? '')),
            surname:  trim((string) ($input['surname'] ?? '')),
            password: (string) ($input['password'] ?? ''),
        );
    }
}
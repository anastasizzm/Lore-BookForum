<?php
declare(strict_types=1);

namespace App\Forms\Auth;

use App\Forms\Form;

final class LoginForm implements Form
{
    public function __construct(
        public string $login,
        public string $password,
    ) {}

    public static function fromInput(array $input): self
    {
        return new self(
            login:    mb_strtolower(trim((string) ($input['login'] ?? ''))),
            password: (string) ($input['password'] ?? ''),
        );
    }

    public function validate(array &$errors): bool
    {
        $ok = true;
        if ($this->login === '') {
            $errors['login'][] = 'Login is required';
            $ok = false;
        }

        if (strlen($this->password) == 0) {
            $errors['password'][] = 'Password cannot be empty';
            $ok = false;
        }

        return $ok;
    }
}
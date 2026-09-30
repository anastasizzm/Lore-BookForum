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

    public function validate(array &$errors): bool
    {
        $ok = true;

        if (!preg_match('#^[A-Za-z0-9_\.-]{3,}$#', $this->username)) {
            $errors['username'][] = 'Invalid username format';
            $ok = false;
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Invalid email format';
            $ok = false;
        }

        if ($this->name === '') {
            $errors['name'][] = 'Name is required';
            $ok = false;
        }

        if ($this->surname === '') {
            $errors['surname'][] = 'Surname is required';
            $ok = false;
        }

        if (strlen($this->password) < 8) {
            $errors['password'][] = 'Password must be at least 8 characters';
            $ok = false;
        }

        return $ok;
    }
}
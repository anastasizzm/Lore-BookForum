<?php
declare(strict_types=1);

namespace App\Forms\Auth;

use App\Forms\Form;

final readonly class MailOnlyForm implements Form
{
    public function __construct(
        public string $email,
    ) {}

    public static function fromInput(array $input): self
    {
        return new self(
            email: mb_strtolower(trim((string) ($input['email'] ?? ''))),
        );
    }

    public function validate(array &$errors): bool
    {
        $ok = true;

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Invalid email format';
            $ok = false;
        }

        return $ok;
    }
}
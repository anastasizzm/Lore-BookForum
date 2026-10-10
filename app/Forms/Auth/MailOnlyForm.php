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
}
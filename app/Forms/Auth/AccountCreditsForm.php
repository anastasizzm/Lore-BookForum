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

    public function validate(array &$errors) : bool
    {
        $ok = true;

        if (!preg_match('#^[A-Za-z0-9_\.-]{3,}$#', $this->username))
        {
            $errors['username'][] = "Invalid username format";
            $ok = false;
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL))
        {
            $errors['email'][] = "Invalid email format";
            $ok = false;
        }

        return $ok;
    } 
}
<?php
declare(strict_types=1);

namespace App\Forms\Users;

use App\Forms\Form;

final readonly class UserForm implements Form
{
    public function __construct(
        public string $username,
        public string $email,
        public string $avatar,
        public string $name,
        public string $surname,
        public string $bio
    ){}

    public static function fromInput(array $input): self
    {
        return new self(
            username: trim($input['username'] ?? ''),
            email: mb_strtolower(trim($input['email'] ?? '')),
            avatar: trim($input['avatar'] ?? ''),
            name:trim( $input['name'] ?? ''),
            surname: trim($input['surname'] ?? ''),
            bio: trim($input['bio'] ?? '')
        );
    }

    public function validate(array &$errors) : bool
    {
        if (!preg_match('#^[A-Za-z0-9_\.-]{3,}$#', $this->username))
            $errors['username'][] = "Invalid username format";

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL))
            $errors['email'][] = "Invalid email format";

        if (empty($this->avatar))
            $errors['avatar'][] = "Avatar can't be empty";

        if (empty($this->name))
            $errors['name'][] = "Name can't be empty";

        if (empty($this->surname))
            $erorrs['surname'][] = "Surname can't be empty";
    } 
}
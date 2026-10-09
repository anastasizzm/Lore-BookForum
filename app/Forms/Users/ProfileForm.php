<?php
declare(strict_types=1);

namespace App\Forms\Users;

use App\Forms\Form;

final readonly class ProfileForm implements Form
{
    public function __construct(
        public string $avatar,
        public string $name,
        public string $surname,
        public string $bio
    ){}

    public static function fromInput(array $input): self
    {
        return new self(
            avatar: trim($input['avatar'] ?? ''),
            name:trim( $input['name'] ?? ''),
            surname: trim($input['surname'] ?? ''),
            bio: trim($input['bio'] ?? '')
        );
    }
}
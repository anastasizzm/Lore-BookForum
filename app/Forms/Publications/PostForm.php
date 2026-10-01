<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Form;

final readonly class PostForm implements Form
{
    public function __construct(
        public int $publicationId,
        public string $content
    ){}

    public static function fromInput(array $input) : self
    {
        return new self(
            publicationId: (int)($input['publicationId'] ?? 0),
            content: trim($input['content'])
        );
    }

    public function validate(array &$errors) : bool
    {
        $ok = true;
        if (empty($this->publicationId))
        {
            $errors['publicationId'][] = 'The publication cant be empty';
            $ok = false;
        }

        $lenStr = strlen($this->content);
        if ($lenStr < 1 || $lenStr > 2000)
        {
            $errors['content'][] = 'The length must be between 1 and 2000';
            $ok = false;
        }

        return $ok;
    }
}
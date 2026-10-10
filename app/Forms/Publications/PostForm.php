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
}
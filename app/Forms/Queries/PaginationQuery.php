<?php
declare(strict_types=1);

namespace App\Forms\Queries;

use App\Forms\Form;

final class PaginationQuery implements Form
{
    public function __construct(
        public string $page,
        public string $pageSize,
    ) {}

    public static function fromArray(array $input): self
    {
        return new self(
            page:    max((int)($input['page']), 1),
            pageSize: min((int)($input['ps'] ?? 25), 50),
        );
    }

    public function validate(): array
    {
        $errors = [];

        if ($this->page < 1) {
            $errors['page'][] = 'Page must be greater than 1';
        }

        if ($this->pageSize < 1 || $this->pageSize > 50) {
            $errors['ps'][] = 'Page size must be greater between 1 and 50';
        }

        return $errors;
    }
}
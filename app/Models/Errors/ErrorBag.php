<?php
declare(strict_types=1);

namespace App\Models\Errors;

final class ErrorBag
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * @param string       $field
     * @param list<string> $messages
     */
    public function add(string $field, array $messages): void
    {
        if ($messages === []) {
            return;
        }

        $this->errors[$field] = array_merge(
            $this->errors[$field] ?? [],
            $messages,
        );
    }

    public function merge(self $other): void
    {
        foreach ($other->errors as $field => $messages) {
            $this->add($field, $messages);
        }
    }

    public function isEmpty(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->errors;
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}
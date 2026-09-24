<?php
declare(strict_types=1);

namespace App\Lib;

final class Validator
{
    private array $errors = [];

    public function __construct(private array $data) {}

    public function required(string $field, ?string $label = null): self
    {
        $v = $this->data[$field] ?? null;
        if ($v === null || (is_string($v) && trim($v) === '')) {
            $this->errors[$field] = ($label ?? $field) . ' is required';
        }
        return $this;
    }

    public function email(string $field): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Invalid email address';
        }
        return $this;
    }

    public function min(string $field, int $len, ?string $label = null): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if ($v !== '' && mb_strlen($v) < $len) {
            $this->errors[$field] = ($label ?? $field) . " must be at least $len characters";
        }
        return $this;
    }

    public function max(string $field, int $len, ?string $label = null): self
    {
        $v = (string) ($this->data[$field] ?? '');
        if (mb_strlen($v) > $len) {
            $this->errors[$field] = ($label ?? $field) . " must be at most $len characters";
        }
        return $this;
    }

    public function integer(string $field, ?string $label = null): self
    {
        $v = $this->data[$field] ?? null;
        if ($v !== null && $v !== '' && filter_var($v, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = ($label ?? $field) . ' must be an integer';
        }
        return $this;
    }

    public function in(string $field, array $allowed): self
    {
        $v = $this->data[$field] ?? null;
        if ($v !== null && !in_array($v, $allowed, true)) {
            $this->errors[$field] = "Invalid value for $field";
        }
        return $this;
    }

    public function fails(): bool { return $this->errors !== []; }
    public function errors(): array { return $this->errors; }

    /** Throw 422 if any field failed. Returns the (unchanged) data on success. */
    public function validate(): array
    {
        if ($this->errors) {
            throw new HttpException('Validation failed', 422, ['errors' => $this->errors]);
        }
        return $this->data;
    }
}
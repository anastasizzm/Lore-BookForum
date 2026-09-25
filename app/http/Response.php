<?php
declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class Response
{
    /** @var array<string, list<string>> lowercased name => list of values */
    private array $headers = [];

    private string $content;

    public function __construct(
        string $content = '',
        private int $status = 200,
        array $headers = [],
    ) {
        $this->assertValidStatus($status);
        $this->content = $content;

        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, (string) $value);
        }
    }

    // ---------- factories ----------

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        try {
            $body = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to encode JSON response', 0, $e);
        }

        return new self($body, $status, $headers + [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, $headers + [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    public static function text(string $text, int $status = 200, array $headers = []): self
    {
        return new self($text, $status, $headers + [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        if ($status < 300 || $status >= 400) {
            throw new InvalidArgumentException("Redirect status must be 3xx, got $status");
        }

        return new self('', $status, ['Location' => $to]);
    }

    // ---------- immutable "with" ----------

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->setHeader($name, $value);
        return $clone;
    }

    public function withAddedHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->addHeader($name, $value);
        return $clone;
    }

    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);
        return $clone;
    }

    public function withStatus(int $status): self
    {
        $this->assertValidStatus($status);
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withContent(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;
        return $clone;
    }

    // ---------- accessors ----------

    public function status(): int { return $this->status; }
    public function content(): string { return $this->content; }

    /** @return array<string, list<string>> */
    public function headers(): array { return $this->headers; }

    public function getHeader(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;
        return $values === null ? null : implode(', ', $values);
    }

    // ---------- sending ----------

    public function send(string $requestMethod = 'GET'): void
    {
        $isHead = strtoupper($requestMethod) === 'HEAD';
        $isBodyless = in_array($this->status, [204, 304], true);

        if (!headers_sent()) {
            http_response_code($this->status);

            // Content-Length mirrors what GET would return (matters for HEAD).
            if ($this->content !== '' && !$isBodyless && $this->getHeader('Content-Length') === null) {
                header('Content-Length: ' . strlen($this->content));
            }

            foreach ($this->headers as $name => $values) {
                $canonical = $this->canonicalName($name);
                foreach ($values as $i => $value) {
                    // First value replaces, later ones append (Set-Cookie).
                    header("$canonical: $value", $i === 0);
                }
            }
        }

        if ($isHead || $isBodyless) {
            return;
        }

        echo $this->content;
    }

    // ---------- internals ----------

    private function setHeader(string $name, string $value): void
    {
        $this->assertValidHeaderName($name);
        $this->assertValidHeaderValue($value);
        $this->headers[strtolower($name)] = [$value];
    }

    private function addHeader(string $name, string $value): void
    {
        $this->assertValidHeaderName($name);
        $this->assertValidHeaderValue($value);
        $this->headers[strtolower($name)][] = $value;
    }

    private function assertValidStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException("Invalid HTTP status: $status");
        }
    }

    private function assertValidHeaderName(string $name): void
    {
        if ($name === '' || !preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $name)) {
            throw new InvalidArgumentException("Invalid header name: $name");
        }
    }

    private function assertValidHeaderValue(string $value): void
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new InvalidArgumentException('Header value contains CR/LF');
        }
    }

    private function canonicalName(string $lowercase): string
    {
        return implode('-', array_map('ucfirst', explode('-', $lowercase)));
    }
}
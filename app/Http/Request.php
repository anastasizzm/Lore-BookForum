<?php
declare(strict_types=1);

namespace App\Http;

use JsonException;
use HttpException;

final class Request
{
    private ?array $bodyCache = null;

    /** @var array<string, mixed> */
    public array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly array $cookies,
        public readonly array $files,
        public readonly array $server,
    ) {}

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path   = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $headers[$name] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            $method,
            $path,
            $_GET,
            $headers,
            $_COOKIE,
            $_FILES,
            $_SERVER,
        );
    }

    public function body(): array
    {
        if ($this->bodyCache !== null) return $this->bodyCache;

        $raw  = file_get_contents('php://input') ?: '';
        $type = $this->headers['content-type'] ?? '';

        if ($raw !== '' && str_contains($type, 'application/json')) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $this->bodyCache = is_array($decoded) ? $decoded : [];
            } catch (JsonException) {
                throw new HttpException('Malformed JSON body', 400);
            }
        } elseif ($raw !== '' && str_contains($type, 'application/x-www-form-urlencoded')) {
            parse_str($raw, $parsed);
            $this->bodyCache = $parsed;
        } elseif ($this->method === 'POST' && $_POST !== []) {
            $this->bodyCache = $_POST;
        } else {
            $this->bodyCache = [];
        }

        return $this->bodyCache;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body()[$key] ?? $this->query[$key] ?? $default;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function getCookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function file(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path, '/api/');
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function setAttribute(string $key, mixed $value) : void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
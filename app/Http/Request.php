<?php
declare(strict_types=1);

namespace App\Http;

use JsonException;
use HttpException;
use App\ErrorCodes;

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
            self::normalizeFiles($_FILES),
            $_SERVER,
        );
    }

    public function body(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $contentType = $this->headers['content-type'] ?? '';

        // 1. multipart/form-data
        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->bodyCache = $_POST;
        }

        // 2. GET, HEAD, DELETE без body, пустой POST.
        if (!$this->hasBody()) {
            return $this->bodyCache = [];
        }

        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return $this->bodyCache = [];
        }

        // 3. JSON
        if (str_contains($contentType, 'application/json')) {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new HttpException('Malformed JSON body', 400, ErrorCodes::JSON_BAD_BODY);
            }

            if (!is_array($decoded)) {
                throw new HttpException('JSON body must be an object or array', 400, ErrorCodes::JSON_BAD_BODY);
            }

            return $this->bodyCache = $decoded;
        }

        // 4. application/x-www-form-urlencoded
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($raw, $parsed);
            return $this->bodyCache = $parsed;
        }

        // 5. Неизвестный content-type с телом
        return $this->bodyCache = [];
    }

    private function hasBody(): bool
    {
        $length = $this->server['CONTENT_LENGTH'] ?? null;

        if ($length !== null && (int) $length > 0) {
            return true;
        }

        $transferEncoding = $this->server['HTTP_TRANSFER_ENCODING'] ?? '';
        return str_contains(strtolower((string) $transferEncoding), 'chunked');
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

    private static function normalizeFiles(array $files): array
    {
        $result = [];

        foreach ($files as $field => $file) {
            $result[$field] = self::normalizeField($file);
        }

        return $result;
    }  
    
    private static function normalizeField(array $file): array
    {
        if (!is_array($file['name'] ?? null)) {
            return [$file];
        }

        $result = [];
        $count  = count($file['name']);

        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'name'     => $file['name'][$i],
                'type'     => $file['type'][$i],
                'tmp_name' => $file['tmp_name'][$i],
                'error'    => $file['error'][$i],
                'size'     => $file['size'][$i],
            ];
        }

        return $result;
    }
}
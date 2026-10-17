<?php
declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class Response
{
    /** @var array<string, list<string>> lowercased name => list of values */
    private array $headers = [];

    /** @var string|resource|callable(): void */
    private mixed $body;

    /** @var array<int, true> статусы, при которых тело не отправляется */
    private const BODYLESS_STATUSES = [204, 304];

    public function __construct(
        mixed $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        $this->assertValidStatus($status);
        $this->assertValidBody($body);

        $this->body = $body;

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

    /**
     * Стриминговый ответ. Тело будет вызвано в момент send().
     *
     * @param callable(): void $writer  пишет в output; вызывается один раз
     * @param array<string, string> $headers  должен содержать Content-Length/Content-Range сам
     */
    public static function stream(
        callable $writer,
        int $status = 200,
        array $headers = [],
    ): self {
        return new self($writer, $status, $headers);
    }

    /**
     * Отдаёт локальный файл как поток. Парсинг Range — на вызывающем.
     *
     * @param int|null $length  сколько байт читать (null — до конца)
     */
    public static function file(
        string $path,
        int $status = 200,
        array $headers = [],
        int $offset = 0,
        ?int $length = null,
    ): self {
        $writer = static function () use ($path, $offset, $length): void {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;   // headers уже ушли, менять статус нельзя
            }

            try {
                if ($offset > 0) {
                    fseek($handle, $offset);
                }

                $remaining = $length;

                while (!feof($handle)) {
                    if (connection_aborted()) {
                        return;
                    }

                    $chunkSize = $remaining === null
                        ? 8192
                        : min(8192, $remaining);

                    if ($chunkSize <= 0) {
                        break;
                    }

                    $chunk = fread($handle, $chunkSize);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    echo $chunk;

                    if ($remaining !== null) {
                        $remaining -= strlen($chunk);
                    }

                    flush();
                }
            } finally {
                fclose($handle);
            }
        };

        return new self($writer, $status, $headers);
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

    /** @param string|resource|callable(): void $body */
    public function withContent(mixed $body): self
    {
        $this->assertValidBody($body);
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }

    // ---------- accessors ----------

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Строковое тело. Для streamed-ответов возвращает пустую строку —
     * middleware не должен читать бинарный поток.
     */
    public function content(): string
    {
        return is_string($this->body) ? $this->body : '';
    }

    public function isStreamed(): bool
    {
        return !is_string($this->body);
    }

    /** @return array<string, list<string>> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;
        return $values === null ? null : implode(', ', $values);
    }

    // ---------- sending ----------

    public function send(string $requestMethod = 'GET'): void
    {
        $isHead = strtoupper($requestMethod) === 'HEAD';
        $isBodyless = in_array($this->status, self::BODYLESS_STATUSES, true);

        // 1. Если тело streamed — снимаем буферы до отправки заголовков.
        //    Иначе PHP копит бинарник в памяти и ломает стриминг.
        if ($this->isStreamed()) {
            $this->prepareForStreaming();
        }

        // 2. Заголовки
        if (!headers_sent()) {
            http_response_code($this->status);

            // Content-Length — только для строкового тела.
            // Для потока Content-Length должен задать вызывающий (иначе chunked).
            if (is_string($this->body)
                && $this->body !== ''
                && !$isBodyless
                && $this->getHeader('Content-Length') === null
            ) {
                header('Content-Length: ' . strlen($this->body));
            }

            foreach ($this->headers as $name => $values) {
                $canonical = $this->canonicalName($name);
                $isSetCookie = strtolower($name) === 'set-cookie';

                foreach ($values as $i => $value) {
                    // Первое значение заменяет, последующие добавляют.
                    // Set-Cookie всегда добавляет — иначе куки затирают друг друга.
                    $replace = !$isSetCookie && $i === 0;
                    header("$canonical: $value", $replace);
                }
            }
        }

        // 3. HEAD и 204/304 — без тела
        if ($isHead || $isBodyless) {
            return;
        }

        // 4. Тело
        $this->emitBody();
    }

    // ---------- internals ----------

    private function emitBody(): void
    {
        $body = $this->body;

        if (is_string($body)) {
            echo $body;
            return;
        }

        if (is_resource($body)) {
            fpassthru($body);
            fclose($body);
            return;
        }

        // callable
        try {
            $body();
        } catch (Throwable $e) {
            // Headers уже отправлены — новый Response нельзя.
            // Логируем и обрываем соединение.
            error_log('Streaming body failed: ' . $e->getMessage());
        }
    }

    private function prepareForStreaming(): void
    {
        // 1. Снять все output-буферы. Иначе PHP будет копить бинарник.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 2. Отключить gzip — PDF уже сжат, повторное сжатие жжёт CPU и портит данные.
        ini_set('zlib.output_compression', '0');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        // 3. Не давать PHP буферизовать вывод.
        ini_set('output_buffering', '0');
        ini_set('implicit_flush', '1');

        // 4. Снять лимит времени — большие файлы качаются долго.
        set_time_limit(0);
    }

    private function assertValidBody(mixed $body): void
    {
        if (is_string($body) || is_resource($body) || is_callable($body)) {
            return;
        }

        throw new InvalidArgumentException(
            'Body must be string, resource, or callable, got ' . get_debug_type($body)
        );
    }

    private function assertValidStatus(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException("Invalid HTTP status: $status");
        }
    }

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
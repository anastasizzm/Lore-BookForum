<?php
declare(strict_types=1);

namespace App\Lib\I18n;

use JsonException;
use RuntimeException;

final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private array $loaded = [];

    private string $locale;

    public function __construct(
        private readonly string $langPath,
        private readonly string $fallback,
        private readonly array  $available,
        ?string $initialLocale = null,
    ) {
        $this->locale = $this->normalize($initialLocale ?? $fallback);
    }

    // ---------- API ----------

    public function locale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $this->normalize($locale);
    }

    /** @return list<string> */
    public function available(): array
    {
        return $this->available;
    }

    public function fallback(): string
    {
        return $this->fallback;
    }

    /**
     * @param array<string, scalar> $params
     */
    public function t(string $key, array $params = []): string
    {
        [$file, $path] = $this->splitKey($key);

        $value = $this->lookup($this->locale, $file, $path);

        if ($value === null && $this->locale !== $this->fallback) {
            $value = $this->lookup($this->fallback, $file, $path);
        }

        if ($value === null) {
            return $key;
        }

        return $this->interpolate($value, $params);
    }

    public function has(string $key): bool
    {
        [$file, $path] = $this->splitKey($key);

        return $this->lookup($this->locale, $file, $path) !== null
            || $this->lookup($this->fallback, $file, $path) !== null;
    }

    // ---------- internals ----------

    private function normalize(string $locale): string
    {
        return in_array($locale, $this->available, true)
            ? $locale
            : $this->fallback;
    }

    /** @return array{0: string, 1: string} */
    private function splitKey(string $key): array
    {
        $dot = strpos($key, '.');

        if ($dot === false) {
            throw new RuntimeException(
                "Translation key must be 'file.key', got: '$key'"
            );
        }

        return [substr($key, 0, $dot), substr($key, $dot + 1)];
    }

    private function lookup(string $locale, string $file, string $path): ?string
    {
        $data = $this->loadFile($locale, $file);

        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }

        return is_string($data) ? $data : null;
    }

    /** @return array<string, mixed> */
    private function loadFile(string $locale, string $file): array
    {
        $cacheKey = "$locale:$file";

        if (isset($this->loaded[$cacheKey])) {
            return $this->loaded[$cacheKey];
        }

        $path = $this->langPath . '/' . $locale . '/' . $file . '.json';

        if (!is_file($path)) {
            return $this->loaded[$cacheKey] = [];
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return $this->loaded[$cacheKey] = [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid JSON in $path: {$e->getMessage()}");
        }

        if (!is_array($data)) {
            throw new RuntimeException("Translation file must be a JSON object: $path");
        }

        return $this->loaded[$cacheKey] = $data;
    }

    /**
     * @param array<string, scalar> $params
     */
    private function interpolate(string $message, array $params): string
    {
        if ($params === []) {
            return $message;
        }

        $replacements = [];

        foreach ($params as $key => $value) {
            $replacements[':' . ltrim($key, ':')] = (string) $value;
        }

        return strtr($message, $replacements);
    }
}
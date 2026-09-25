<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\Request;

final class CsrfManager
{
    public const string FIELD = '_token';
    public const string HEADER = 'x-csrf-token';
    public const string COOKIE = 'csrf_token';
    public const int BYTES = 32;

    // ---------- generation ----------

    public static function generate(): string
    {
        return bin2hex(random_bytes(self::BYTES));
    }

    // ---------- extraction ----------

    /**
     * Pull the submitted token from the request.
     * Order: form field → header → cookie.
     */
    public static function extract(Request $request): ?string
    {
        $candidates = [
            $request->input(self::FIELD),
            $request->header(self::HEADER),
            $request->cookies[self::COOKIE] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    // ---------- validation ----------

    /** Constant-time compare of two tokens. */
    public static function isValid(?string $expected, ?string $submitted): bool
    {
        if ($expected === null || $expected === '' || $submitted === null || $submitted === '') {
            return false;
        }

        return hash_equals($expected, $submitted);
    }

    /** Convenience: verify the token carried by the request against an expected value. */
    public static function verify(Request $request, ?string $expected): bool
    {
        return self::isValid($expected, self::extract($request));
    }
}
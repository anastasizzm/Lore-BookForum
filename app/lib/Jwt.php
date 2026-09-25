<?php
declare(strict_types=1);

namespace App\Lib;

final class Jwt
{
    public const TYP_ACCESS  = 'access';
    public const TYP_REFRESH = 'refresh';

    public static function encode(array $payload, string $secret): string
    {
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $body   = self::b64(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig    = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        return "$header.$body.$sig";
    }

    public static function decode(string $token, string $secret, int $leeway = 0): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;

        $expected = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        if (!hash_equals($expected, $sig)) return null;

        $decodedHeader = json_decode(self::unb64($header), true);
        if (!is_array($decodedHeader) || ($decodedHeader['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $raw = self::unb64($body);
        if ($raw === false) return null;

        $claims = json_decode($raw, true);
        if (!is_array($claims)) return null;

        $now = time();
        if (isset($claims['exp']) && $claims['exp'] < ($now - $leeway)) return null;
        if (isset($claims['nbf']) && $claims['nbf'] > ($now + $leeway)) return null;

        return $claims;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
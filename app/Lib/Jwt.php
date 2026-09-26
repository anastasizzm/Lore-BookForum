<?php
declare(strict_types=1);

namespace App\Lib;

use RuntimeException;

final class Jwt
{
    public const TYP_ACCESS  = 'access';
    public const TYP_REFRESH = 'refresh';

    public function __construct(private Settings $settings) {}

    // ---------- convenience issuers ----------

    /** Short-lived token for API calls. */
    public function access(int|string $userId, array $extra = []): string
    {
        return $this->encode(
            $this->buildClaims(self::TYP_ACCESS, $userId, $extra, $this->accessTtl())
        );
    }

    /** Long-lived token used only to mint new access tokens. */
    public function refresh(int|string $userId, array $extra = []): string
    {
        return $this->encode(
            $this->buildClaims(self::TYP_REFRESH, $userId, $extra, $this->refreshTtl())
        );
    }

    public function emailVerification(int $userId, int $ttlSeconds = 86400): string
    {
        $now = time();

        return $this->encode([
            'iss' => $this->settings->jwtIssuer,
            'sub' => $userId->toString(),
            'typ' => 'email_verify',
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ]);
    }

    // ---------- convenience verifiers ----------

    /** Returns claims if the token is a valid access token, null otherwise. */
    public function decodeAccess(string $token): ?array
    {
        $claims = $this->decode($token);

        return ($claims !== null && ($claims['typ'] ?? null) === self::TYP_ACCESS)
            ? $claims
            : null;
    }

    /** Returns claims if the token is a valid refresh token, null otherwise. */
    public function decodeRefresh(string $token): ?array
    {
        $claims = $this->decode($token);

        return ($claims !== null && ($claims['typ'] ?? null) === self::TYP_REFRESH)
            ? $claims
            : null;
    }

    public function decodeEmailVerification(string $token): ?array
    {
        $claims = $this->decode($token);

        return ($claims !== null && ($claims['typ'] ?? null) === 'email_verify')
            ? $claims
            : null;
    }

    // ---------- core ----------

    public function encode(array $payload): string
    {
        $header = self::b64(json_encode(
            ['alg' => 'HS256', 'typ' => 'JWT'],
            JSON_THROW_ON_ERROR
        ));
        $body = self::b64(json_encode($payload, JSON_THROW_ON_ERROR));
        $sig  = self::b64(hash_hmac(
            'sha256',
            "$header.$body",
            $this->secret(),
            true
        ));

        return "$header.$body.$sig";
    }

    public function decode(string $token, int $leeway = 0): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $sig] = $parts;

        $expected = self::b64(hash_hmac(
            'sha256',
            "$header.$body",
            $this->secret(),
            true
        ));

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $decodedHeader = json_decode(self::unb64($header) ?: '', true);
        if (!is_array($decodedHeader) || ($decodedHeader['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $raw = self::unb64($body);
        if ($raw === false) {
            return null;
        }

        $claims = json_decode($raw, true);
        if (!is_array($claims)) {
            return null;
        }

        $now = time();

        if (isset($claims['exp']) && $claims['exp'] < ($now - $leeway)) {
            return null;
        }
        if (isset($claims['nbf']) && $claims['nbf'] > ($now + $leeway)) {
            return null;
        }

        // Optional issuer check — skips if not configured.
        $issuer = $this->settings->jwtIssuer ?? null;
        if ($issuer !== null && ($claims['iss'] ?? null) !== $issuer) {
            return null;
        }

        return $claims;
    }

    // ---------- internals ----------

    private function buildClaims(
        string $typ,
        int|string $userId,
        array $extra,
        int $ttl,
    ): array {
        $now = time();

        return [
            'iss' => $this->settings->jwtIssuer,
            'sub' => (string) $userId,
            'typ' => $typ,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ] + $extra;
    }

    private function secret(): string
    {
        $secret = $this->settings->jwtSecret ?? null;

        if ($secret === null || $secret === '') {
            throw new RuntimeException(
                'JWT secret is not configured (set JWT_SECRET in .env)'
            );
        }
        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'JWT secret must be at least 32 bytes — use a random hex/base64 string'
            );
        }

        return $secret;
    }

    private function accessTtl(): int
    {
        return max(60, $this->settings->jwtAccessTtl ?? 3600); // default 1 hour
    }

    private function refreshTtl(): int
    {
        return max(3600, $this->settings->jwtRefreshTtl ?? 60 * 60 * 24 * 30); // 30 days
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string|false
    {
        // Restore padding so base64_decode() accepts the input in strict mode.
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
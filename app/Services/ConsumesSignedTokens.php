<?php
declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\GoneException;
use App\Lib\I18n\Translator;
use App\Lib\Jwt;

trait ConsumesSignedTokens
{
    abstract protected function tokenType(): string;

    abstract protected function tokenCache(): object;

    abstract protected function tokenJwt(): Jwt;

    abstract protected function tokenTranslator(): Translator;

    protected function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeToken(string $rawToken): array
    {
        $claims = $this->tokenJwt()->decode($rawToken);

        if ($claims === null || ($claims['typ'] ?? null) !== $this->tokenType()) {
            throw new ForbiddenException(
                $this->tokenTranslator()->t('errors.mail.invalid_token')
            );
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeVerifiedToken(string $rawToken): array
    {
        $claims = $this->decodeToken($rawToken);

        if (!$this->tokenJwt()->verify($claims)) {
            throw new ForbiddenException(
                $this->tokenTranslator()->t('errors.mail.invalid_token')
            );
        }

        return $claims;
    }

    protected function extractUserId(array $claims): int
    {
        $userId = $claims['sub'] ?? null;
        if (empty($userId)) {
            throw new BadRequestException(
                $this->tokenTranslator()->t('errors.mail.no_user_data')
            );
        }

        return (int) $userId;
    }

    protected function validateToken(string $rawToken): int
    {
        $claims = $this->decodeVerifiedToken($rawToken);
        $userId = $this->extractUserId($claims);

        $cachedHash = $this->tokenCache()->get($userId);
        if ($cachedHash === null) {
            throw new GoneException(
                $this->tokenTranslator()->t('errors.mail.link_expired')
            );
        }

        if (!hash_equals($cachedHash, $this->fingerprint($rawToken))) {
            throw new ForbiddenException(
                $this->tokenTranslator()->t('errors.mail.invalid_token')
            );
        }

        return $userId;
    }
}
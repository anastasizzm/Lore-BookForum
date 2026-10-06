<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Lib\Jwt;
use App\Lib\Settings;

use App\Http\UrlGenerator;

use App\Services\Mail\Mailer;
use App\Services\Configuration\UnitOfWork;

use App\Cache\Auth\PassResetCache;
use App\Cache\Auth\TokenResetTtlCache;

use App\Repositories\Users\UsersRepository;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;

use PDO;

final class PasswordResetService
{
    public function __construct(
        private readonly Jwt            $jwt,
        private readonly UrlGenerator   $url,
        private readonly Mailer         $mailer,
        private readonly UsersRepository $users,
        private readonly UnitOfWork     $uof,
        private readonly PassResetCache $cache,
        private readonly TokenResetTtlCache $tokenCache,
        private readonly Settings       $settings,
    ) {}

    private const string TOKEN_TYP = 'pass_reset';
    private const int TOKEN_TTL_SECONDS = 1800;
    private const int TOKEN_RESET_TTL_SECONDS = 86400;

    public function startReset(int $userId, string $email) : void 
    {
        $token = $this->createToken($userId);
        $this->cache->set($userId, sha256($token), self::TOKEN_TTL_SECONDS);
        
        $link = rtrim($this->settings->appUrl, '/')
              . $this->url->url('password.reset', ['token' => $token]);

        $this->mailer->send(Email::to(
            $email,
            'Lore profile password reset',
            "<h2>Password reset</h2><p>You received this message because you requested password reset.<br>
            If you didnt request it, ignore the message.<br>
            The link to reset your password: </p><a href=\"$link\">Reset</a>"
        ));
    }

    public function verify(string $token) : int 
    {
        $claims = $this->decodeToken($token);

        if ($claims === null)
            throw new GoneException('The link is invalid or has expired');

        $tokenUserId = (int)$claims['sub'];
        $savedHash = $this->cache->get($tokenUserId);
        if ($savedHash === NULL)
            throw new GoneException('The request has expired');

        $hash = hash('sha256', $rawToken);
        if (!hash_equals($savedHash, $hash))
            throw new ForbiddenException("Request token mismatch");

        return $tokenUserId;
    }

    public function resetPassword(int $userId, string $newHash) : bool
    {
        $ok = $this->uof->transactional(function (PDO $pdo) use ($userId, $newHash): bool {
            $ok = $this->users->updatePassword($userId, $newHash);

            if (!$ok) {
                $exists = $this->users->exists($userId);
                if (!$exists) {
                    throw new NotFoundException('User not found');
                }   
            }

            return $ok;
        });

        if (!$ok) return false;

        $this->tokenCache->set($userId, time(), self::TOKEN_RESET_TTL_SECONDS);
        return true;
    }

    private function createToken(int $userId) : string
    {
        return $this->jwt->custom(
            $userId,
            self::TOKEN_TYP,
            ttlSeconds: 1800
        );
    }

    private function decodeToken(string $token) : ?array 
    {
        $claims = $this->jwt->decodeCustom($token);
        return ($claims !== null && ($claims['typ'] ?? null) === self::TOKEN_TYP)
            ? $claims
            : null;
    }
}
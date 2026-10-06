<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Lib\Jwt;
use App\Lib\Settings;

use App\Http\UrlGenerator;

use App\Cache\Auth\MailVerifyCache;
use App\Services\Mail\Mailer;
use App\Services\Configuration\UnitOfWork;

use App\Repositories\Users\UsersRepository;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\BadRequestException;

use PDO;

final class EmailVerificationService
{
    public function __construct(
        private readonly Jwt            $jwt,
        private readonly UrlGenerator   $url,
        private readonly Mailer         $mailer,
        private readonly UsersRepository $users,
        private readonly MailVerifyCache $cache,
        private readonly Settings       $settings,
    ) {}

    private const TOKEN_TYP = 'email_verify';
    private const int TOKEN_TTL_SECONDS = 1800;

    public function send(int $userId, string $email): void
    {
        $token = $this->createToken($userId, $email);
        $this->cache->set($userId, hash('sha256', $token), self::TOKEN_TTL_SECONDS);

        $link = rtrim($this->settings->appUrl, '/')
              . $this->url->url('verify.mail', ['token' => $token]);

        $this->mailer->send(Email::to(
            $email,
            'Lore email verification',
            "<h2>Welcome to Lore!</h2><p>Please verify your email using this link: </p><a href=\"$link\">Click me</a>"
        ));
    }

    public function verify(string $token) : int 
    {
        $claims = $this->decodeToken($token);

        if ($claims === null || !$this->jwt->verify($claims))
            throw new GoneException('The link is invalid or has expired');

        $tokenUserId = (int)$claims['sub'];
        $savedHash = $this->cache->get($tokenUserId);
        if ($savedHash === NULL)
            throw new GoneException('The request has expired');

        $hash = hash('sha256', $token);
        if (!hash_equals($savedHash, $hash))
            throw new ForbiddenException("Request token mismatch");

        return $tokenUserId;
    }

    public function reSend(string $oldToken) : void 
    {
        $claims = $this->decodeToken($oldToken);
        if ($claims === null)
            throw new BadRequestException('The link is invalid');

        $tokenUserId = (int)$claims['sub'];
        $tokenEmail = $claims['email'];

        $this->send($tokenUserId, $tokenEmail);
    }

    private function createToken(int $userId, string $email) : string
    {
        return $this->jwt->custom(
            $userId,
            self::TOKEN_TYP,
            [
                'email' => $email
            ],
            3600
        );
    }

    private function decodeToken(string $token) : ?array 
    {
        $claims = $this->jwt->decode($token);
        return ($claims !== null && ($claims['typ'] ?? null) === self::TOKEN_TYP)
            ? $claims
            : null;
    }
}
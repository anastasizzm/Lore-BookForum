<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Lib\Jwt;
use App\Lib\Settings;

use App\Http\UrlGenerator;

use App\Services\Mail\Mailer;
use App\Services\Configuration\UnitOfWork;

use App\Repositories\Users\UsersRepository;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;

use PDO;

final class EmailVerificationService
{
    public function __construct(
        private readonly Jwt            $jwt,
        private readonly UrlGenerator   $url,
        private readonly Mailer         $mailer,
        private readonly UsersRepository $users,
        private readonly UnitOfWork     $uof,
        private readonly Settings       $settings,
    ) {}

    private const TOKEN_TYP = 'email_verify';

    public function send(int $userId, string $email): void
    {
        $token = $this->createToken($userId);

        $link = rtrim($this->settings->appUrl, '/')
              . $this->url->url('verify.mail', ['token' => $token]);

        $this->mailer->send(Email::to(
            $email,
            'Lore email verification',
            "<h2>Welcome to Lore!</h2><p>Please verify your email using this link: </p><a href=\"$link\">Click me</a>"
        ));
    }

    /** @throws GoneException */
    /** @throws NotFoundException */
    /** @throws UnauthorizedException */
    public function verify(int $userId, string $token): bool
    {
        $claims = $this->decodeToken($token);

        if ($claims === null) {
            throw new GoneException('The link is invalid or has expired');
        }

        $tokenUserId = (int)$claims['sub'];
        if ($tokenUserId !== $userId)
            throw new UnauthorizedException('You logged in with invalid user. Login with verifying user and try again');

        return $this->uof->transactional(function (PDO $pdo) use ($userId): bool {
            $ok = $this->users->markEmailVerified($userId);

            if (!$ok) {
                $exists = $this->users->exists($userId);
                if (!$exists) {
                    throw new NotFoundException('User not found');
                }   
            }

            return $ok;
        });
    }

    private function createToken(int $userId) : string
    {
        return $this->jwt->custom(
            $userId,
            self::TOKEN_TYP,
            ttlSeconds: 3600
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
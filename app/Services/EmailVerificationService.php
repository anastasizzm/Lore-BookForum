<?php
declare(strict_types=1);

namespace App\Services;

use App\Lib\Jwt;
use App\Lib\Settings;

use App\Http\UrlGenerator;

use App\Services\Mailer;
use App\Services\UnitOfWork;

use App\Repositories\UsersRepository;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\NotFoundException;

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

    public function send(int $userId, string $email): void
    {
        $token = $this->jwt->emailVerification($userId);

        $link = rtrim($this->settings->appUrl, '/')
              . $this->url->url('email.verify', ['token' => $token]);

        $this->mailer->send(Email::to(
            $email,
            'Lore email verification',
            "<h2>Welcome to Lore!</h2><p>Please verify your email using this link: </p><a href=\"$link\">Click me</a>"
        ));
    }

    /** @throws GoneException */
    /** @throws NotFoundException */
    public function verify(string $token): bool
    {
        $claims = $this->jwt->decodeEmailVerification($token);

        if ($claims === null) {
            throw new GoneException('The link is invalid or has expired');
        }

        $userId = (int)$claims['sub'];

        return $this->uof->transactional(function (PDO $pdo) use ($userId): bool {
            $ok = $this->users->markEmailVerified($userId);

            if (!$ok) {
                $exists = $this->users->exists($userId);
                if (!$exists) {
                    throw new NotFoundException('User not found');
                }
            }

            return true;
        });
    }
}
<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Lib\Jwt;
use App\Lib\Settings;
use App\Lib\View;

use App\Http\UrlGenerator;

use App\Services\Mail\Mailer;
use App\Services\Auth\TokenResetService;

use App\Forms\Auth\MailOnlyForm;
use App\Forms\Auth\PassResetForm;
use App\Cache\Auth\PassResetCache;

use App\Repositories\Users\UsersRepository;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;

use PDO;

final class PasswordResetService
{
    private const RESET_TOKEN_TYP = 'pass_reset';
    private const RESET_TOKEN_TTL = 1800;

    public function __construct(
        private readonly Jwt            $jwt,
        private readonly UrlGenerator   $url,
        private readonly Mailer         $mailer,
        private readonly UsersRepository $usersRepo,
        private readonly PassResetCache $cache,
        private readonly TokenResetService $tokenResetService,
        private readonly Settings       $settings,
    ) {}

    public function startReset(MailOnlyForm $form) : void 
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);

        $userId = $this->getCredits($form->email);
        
        $token = $this->issueResetToken($userId);
        $this->cache->set($userId, $this->fingerprint($token), self::RESET_TOKEN_TTL);
        
        $this->mailer->send(Email::to(
            $email,
            'Lore profile password reset',
            View::render('email/reset-password', [
                'resetUrl' => $this->url->fullUrl('password.reset', ['token' => $token])
            ]),
        ));
    }

    public function completeReset(PassResetForm $form): void
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);

        $userId = $this->consumeToken($form->token);
        $this->users->changePassword($userId, password_hash($form->password, PASSWORD_DEFAULT));

        $this->tokenResetService->resetFromUser($userId);
    }

    private function getCredits(string $email) : AccountCredits 
    {
        $id = $this->usersRepo->identifyByLogin($email);
        if ($id === null)
            throw new ValidationException(['email' => ['Account with this email not found']]);
    
        return $id;
    }

    private function consumeToken(string $rawToken): int
    {
        $claims = $this->decodeVerifiedToken($rawToken);
        $userId = $this->extractUserId($claims);

        $cachedHash = $this->cache->get($userId);
        if ($cachedHash === null) {
            throw new GoneException('The link has expired');
        }

        if (!hash_equals($cachedHash, $this->fingerprint($rawToken))) {
            throw new ForbiddenException('Invalid token');
        }

        $this->cache->delete($userId);
        return $userId;
    }

    private function decodeToken(string $rawToken) : array 
    {
        $claims = $this->jwt->decode($rawToken);

        if ($claims === null || ($claims['typ'] ?? null) !== self::VERIFY_TOKEN_TYP
        ) {
            throw new ForbiddenException('Token is invalid');
        }

        return $claims;
    }

    private function decodeVerifiedToken(string $rawToken): array
    {
        $claims = $this->decodeToken($rawToken);

        if (!$this->jwt->verify($claims))
            throw new ForbiddenException('Token is invalid');

        return $claims;
    }

    private function extractUserId(array $claims): int
    {
        $userId = $claims['sub'] ?? null;
        if (empty($userId)) {
            throw new BadRequestException('No user data provided');
        }

        return (int) $userId;
    }

    private function issueResetToken(int $userId, string $email): string
    {
        return $this->jwt->custom(
            $userId,
            self::RESET_TOKEN_TYP,
            ttlSeconds: self::RESET_TOKEN_TTL,
        );
    }

    private function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }
}
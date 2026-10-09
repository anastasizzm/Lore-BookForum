<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Lib\Jwt;
use App\Lib\Settings;
use App\Lib\View;
use App\Lib\I18n\Translator;

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
        private readonly Translator $translator
    ) {}

    public function startReset(MailOnlyForm $form) : void 
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);

        $userId = $this->getId($form->email);
        
        $token = $this->issueResetToken($userId);
        $this->cache->set($userId, $this->fingerprint($token), self::RESET_TOKEN_TTL);
        
        $this->mailer->send(Email::to(
            $form->email,
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
        $this->cache->forget($userId);
        $this->usersRepo->changePassword($userId, password_hash($form->password, PASSWORD_DEFAULT));
        $this->tokenResetService->resetFromUser($userId);
    }

    private function getId(string $email) : int 
    {
        $id = $this->usersRepo->identifyByLogin($email);
        if ($id === null)
            throw new ValidationException(['email' => [$this->translator->t("item_based.not_found", [":item" => $this->translator->t("display_names.account.m")])]]);
    
        return $id;
    }

    public function consumeToken(string $rawToken): int
    {
        $claims = $this->decodeVerifiedToken($rawToken);
        $userId = $this->extractUserId($claims);

        $cachedHash = $this->cache->get($userId);
        if ($cachedHash === null) {
            throw new GoneException($this->translator->t("errors.mail.link_expired"));
        }

        if (!hash_equals($cachedHash, $this->fingerprint($rawToken))) {
            throw new ForbiddenException($this->translator->t("errors.mail.invalid_token"));
        }

        return $userId;
    }

    private function decodeToken(string $rawToken) : array 
    {
        $claims = $this->jwt->decode($rawToken);

        if ($claims === null || ($claims['typ'] ?? null) !== self::RESET_TOKEN_TYP
        ) {
            throw new ForbiddenException($this->translator->t("errors.mail.invalid_token"));
        }

        return $claims;
    }

    private function decodeVerifiedToken(string $rawToken): array
    {
        $claims = $this->decodeToken($rawToken);

        if (!$this->jwt->verify($claims))
            throw new ForbiddenException($this->translator->t("errors.mail.invalid_token"));

        return $claims;
    }

    private function extractUserId(array $claims): int
    {
        $userId = $claims['sub'] ?? null;
        if (empty($userId)) {
            throw new BadRequestException($this->translator->t("errors.mail.no_user_data"));
        }

        return (int) $userId;
    }

    private function issueResetToken(int $userId): string
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
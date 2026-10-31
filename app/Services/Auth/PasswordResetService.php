<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Lib\Jwt;
use App\Lib\View;
use App\Lib\I18n\Translator;

use App\Http\UrlGenerator;

use App\Services\Mail\Mailer;
use App\Services\Auth\TokenResetService;
use App\Services\ConsumesSignedTokens;

use App\Forms\Auth\MailOnlyForm;
use App\Forms\Auth\PassResetForm;
use App\Cache\Auth\PassResetCache;

use App\Repositories\Users\UsersRepository;

use App\Validators\Auth\MailOnlyFormValidator;
use App\Validators\Auth\PassResetFormValidator;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;

final class PasswordResetService
{
    use ConsumesSignedTokens;

    private const RESET_TOKEN_TYP = 'pass_reset';
    private const RESET_TOKEN_TTL = 1800;

    public function __construct(
        private readonly Jwt                    $jwt,
        private readonly UrlGenerator           $url,
        private readonly Mailer                 $mailer,
        private readonly UsersRepository        $usersRepo,
        private readonly PassResetCache         $cache,
        private readonly TokenResetService      $tokenResetService,
        private readonly Translator             $translator,
        private readonly MailOnlyFormValidator  $mailFormValidator,
        private readonly PassResetFormValidator $passResetFormValidator,
    ) {}

    // ---- trait

    protected function tokenType(): string   { return self::RESET_TOKEN_TYP; }
    protected function tokenCache(): object  { return $this->cache; }
    protected function tokenJwt(): Jwt       { return $this->jwt; }
    protected function tokenTranslator(): Translator { return $this->translator; }

    // ---- public

    public function startReset(MailOnlyForm $form, string $redirectUrl): void
    {
        $errorBag = $this->mailFormValidator->validateOne($form);
        if (!$errorBag->isEmpty()) {
            throw new ValidationException($errorBag->all());
        }

        $userId = $this->getId($form->email);

        $token = $this->issueResetToken($userId);
        $this->cache->set($userId, $this->fingerprint($token), self::RESET_TOKEN_TTL);

        $this->mailer->send(Email::to(
            $form->email,
            'Lore profile password reset',
            View::render('email/reset-password', [
                'resetUrl' => $this->url->fullUrl('password.reset', ['token' => $token], ['redirect' => $redirectUrl]),
            ]),
        ));
    }

    public function completeReset(PassResetForm $form): void
    {
        $errorBag = $this->passResetFormValidator->validateOne($form);
        if (!$errorBag->isEmpty()) {
            throw new ValidationException($errorBag->all());
        }

        $userId = $this->validateToken($form->token);

        $this->usersRepo->changePassword(
            $userId,
            password_hash($form->password, PASSWORD_DEFAULT),
        );

        $this->cache->forget($userId);
        $this->tokenResetService->resetFromUser($userId);
    }

    // ---- service-specific

    private function getId(string $email): int
    {
        $id = $this->usersRepo->identifyByLogin($email);
        if ($id === null) {
            throw new ValidationException([
                'email' => [
                    $this->translator->t('item_based.not_found.m', [
                        ':item' => $this->translator->t('display_names.account'),
                    ]),
                ],
            ]);
        }
        return $id;
    }

    private function issueResetToken(int $userId): string
    {
        return $this->jwt->custom(
            $userId,
            self::RESET_TOKEN_TYP,
            ttlSeconds: self::RESET_TOKEN_TTL,
        );
    }
}
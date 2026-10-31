<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\ErrorCodes;

use App\Lib\Jwt;
use App\Lib\View;

use App\Http\UrlGenerator;
use App\Http\HttpException;

use App\Cache\Auth\MailVerifyCache;
use App\Services\Mail\Mailer;
use App\Services\Auth\TokenResetService;
use App\Services\ConsumesSignedTokens;
use App\Services\Configuration\UnitOfWork;

use App\Repositories\Users\UsersRepository;

use App\Models\Email;

use App\Exceptions\GoneException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\BadRequestException;

use App\Lib\I18n\Translator;

use PDO;

final class EmailVerificationService
{
    use ConsumesSignedTokens;

    private const VERIFY_TOKEN_TYP = 'email_verify';
    private const VERIFY_TOKEN_TTL = 3600;

    public function __construct(
        private readonly Jwt               $jwt,
        private readonly UrlGenerator      $url,
        private readonly Mailer            $mailer,
        private readonly UsersRepository   $users,
        private readonly MailVerifyCache   $cache,
        private readonly TokenResetService $tokenResetService,
        private readonly UnitOfWork        $uow,
        private readonly Translator        $translator,
    ) {}

    // ---- trait

    protected function tokenType(): string   { return self::VERIFY_TOKEN_TYP; }
    protected function tokenCache(): object  { return $this->cache; }
    protected function tokenJwt(): Jwt       { return $this->jwt; }
    protected function tokenTranslator(): Translator { return $this->translator; }

    // ---- public

    public function startVerification(int $userId, string $email): void
    {
        $token = $this->issueVerifyToken($userId, $email);
        $this->cache->set($userId, $this->fingerprint($token), self::VERIFY_TOKEN_TTL);

        $this->mailer->send(Email::to(
            $email,
            'Lore email verification',
            View::render('email/verify-email', [
                'verifyUrl' => $this->url->fullUrl('verify.mail', ['token' => $token]),
            ]),
        ));
    }

    public function restartVerification(string $oldToken): void
    {
        $claims = $this->decodeToken($oldToken);
        $userId = $this->extractUserId($claims);
        $mail   = $this->extractEmail($claims);

        $this->startVerification($userId, $mail);
    }

    public function completeVerification(string $rawToken): string
    {
        $userId = $this->validateToken($rawToken);

        if ($this->users->checkVerified($userId)) {
            throw new HttpException(
                $this->translator->t('errors.mail.already_verified'),
                302,
                ErrorCodes::ALREADY_DONE,
            );
        }

        $ok = $this->uow->transactional(
            fn (PDO $pdo): bool => $this->users->markEmailVerified($userId),
        );

        if (!$ok) {
            throw new NotFoundException(
                $this->translator->t('item_based.not_found.m', [
                    ':item' => $this->translator->t('display_names.user'),
                ]),
            );
        }

        $this->cache->forget($userId);
        $this->tokenResetService->resetFromUser($userId);
        return $this->issueSessionToken($userId);
    }

    // ---- service-specific

    private function extractEmail(array $claims): string
    {
        $email = $claims['email'] ?? null;
        if (empty($email)) {
            throw new BadRequestException(
                $this->translator->t('errors.mail.no_user_data'),
            );
        }
        return $email;
    }

    private function issueVerifyToken(int $userId, string $email): string
    {
        return $this->jwt->custom(
            $userId,
            self::VERIFY_TOKEN_TYP,
            ['email' => $email],
            self::VERIFY_TOKEN_TTL,
        );
    }

    private function issueSessionToken(int $userId): string
    {
        return $this->jwt->access($userId, ['verified' => '1']);
    }
}
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
    private const VERIFY_TOKEN_TYP = 'email_verify';
    private const VERIFY_TOKEN_TTL = 3600;

    public function __construct(
        private readonly Jwt             $jwt,
        private readonly UrlGenerator    $url,
        private readonly Mailer          $mailer,
        private readonly UsersRepository $users,
        private readonly MailVerifyCache $cache,
        private readonly TokenResetService $tokenResetService,
        private readonly UnitOfWork      $uow,
        private readonly Translator $translator
    ) {}

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

    public function restartVerification(string $oldToken) : void 
    {
        $claims = $this->decodeToken($oldToken);
        $userId = $this->extractUserId($claims);
        $mail = $this->extractEmail($claims);

        $this->startVerification($userId, $mail);
    }

    public function completeVerification(string $rawToken): string
    {
        $userId = $this->consumeToken($rawToken);
        if ($this->checkVerificationExists($userId))
            throw new HttpException($this->translator->t("errors.mail.already_verified"), 302, ErrorCodes::ALREADY_DONE);

        $ok = $this->uow->transactional(function (PDO $pdo) use ($userId): bool {
            return $this->users->markEmailVerified($userId);
        });

        if (!$ok) throw new NotFoundException($this->translator->t("item_based.not_found", [':item' => $this->translator->t('display_names.user')]));
        
        $this->tokenResetService->resetFromUser($userId);
        return $this->issueSessionToken($userId);
    }

    private function checkVerificationExists(int $userId) : bool 
    {
        return $this->users->checkVerified($userId);
    }

    private function consumeToken(string $rawToken): int
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

        // One-shot: the link dies the moment it is redeemed.
        $this->cache->forget($userId);

        return $userId;
    }

    private function decodeToken(string $rawToken) : array 
    {
        $claims = $this->jwt->decode($rawToken);

        if ($claims === null || ($claims['typ'] ?? null) !== self::VERIFY_TOKEN_TYP
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

    private function extractEmail(array $claims) : string 
    {
        $email = $claims['email'] ?? null;
        if (empty($email))
            throw new BadRequestException($this->translator->t("errors.mail.no_user_data"));

        return $email;
    }

    private function extractUserId(array $claims): int
    {
        $userId = $claims['sub'] ?? null;
        if (empty($userId)) {
            throw new BadRequestException($this->translator->t("errors.mail.no_email_data"));
        }

        return (int) $userId;
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

    private function fingerprint(string $token): string
    {
        return hash('sha256', $token);
    }
}
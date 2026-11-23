<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\Users\UsersRepository;

use App\Services\Configuration\UnitOfWork;
use App\Services\Auth\EmailVerificationService;

use App\Models\Email;
use App\Models\Auth\AuthCredits;

use App\Lib\Jwt;
use App\Lib\I18n\Translator;
use App\Http\HttpException;

use App\Forms\Auth\AccountCreditsForm;
use App\Validators\Auth\AccountCreditsFormValidator;

use App\Exceptions\ValidationException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\Translators\UserExceptionTranslator;

use PDO;    

final class AccountService 
{
     public function __construct(
        private readonly UsersRepository $usersRepo,
        private readonly UnitOfWork $uow,
        private readonly Jwt $jwt,
        private readonly UserExceptionTranslator $exceptionTranslator,
        private readonly EmailVerificationService $mailVerificationService,
        private readonly Translator $translator,
        private readonly AccountCreditsFormValidator $accountCreditsFormValidator
    ){}

    /**
     * @return string|null New access token with verified=0 when the email
     *                     changed, otherwise null (the current token stays valid).
     */
    public function changeCredits(int $userId, AccountCreditsForm $form) : ?string
    {
        $errorBag = $this->accountCreditsFormValidator->validateOne($form);
        if (!$errorBag->isEmpty()) throw new ValidationException($errorBag->all());
        
        $currentCredits = $this->usersRepo->getAccountCredits($userId);
        if ($currentCredits === NULL) throw new NotFoundException($this->translator->t('errors.common.not_found'));

        try{
            $this->usersRepo->changeCredits($userId, $form->email, $form->username);
            if ($form->email !== $currentCredits->email)
            {
                $this->usersRepo->unmarkEmailVerified($userId);

                // The old cookie still contains verified=1. Mint a fresh token
                // for the same user and let the controller replace the cookie.
                $token = $this->jwt->access($userId, ['verified' => '0']);
                $this->mailVerificationService->startVerification($userId, $form->email);

                return $token;
            }
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }

        return null;
    }
}
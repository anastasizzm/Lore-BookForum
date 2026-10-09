<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Constants;
use App\ErrorCodes;

use App\Repositories\Users\UsersRepository;

use App\Services\Configuration\UnitOfWork;
use App\Services\Auth\EmailVerificationService;

use App\Models\Email;
use App\Models\Auth\AuthCredits;

use App\Lib\Jwt;
use App\Lib\I18n\Translator;
use App\Http\HttpException;

use App\Forms\Auth\AccountCreditsForm;

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
        private readonly Translator $translator
    ){}

    public function changeCredits(int $userId, AccountCreditsForm $form) : void
    {
        $errors = [];
        if (!$form->validate($errors)) throw new ValidationException($errors);
        
        $currentCredits = $this->usersRepo->getAccountCredits($userId);
        if ($currentCredits === NULL) throw new NotFoundException($this->translator->t('errors.common.not_found'));

        try{
            $this->usersRepo->changeCredits($userId, $form->email, $form->username);
            if ($form->email !== $currentCredits->email)
            {
                $this->mailVerificationService->startVerification($userId, $form->email);
                throw new HttpException($this->translator->t('errors.mail.verify_mail'), 202, ErrorCodes::ACCEPTED);
            }
        }
        catch(\PDOException $e){
            throw $this->exceptionTranslator->translate($e);
        }
    }
}
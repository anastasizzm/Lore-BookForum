<?php
declare(strict_types=1);

namespace App\Services\Publications;

use App\Repositories\Publications\PublicationsRepository;
use App\Repositories\Publications\UsersReadRepository;
use App\Services\Configuration\UnitOfWork;
use App\Services\ConsumesSignedTokens;
use App\Services\Users\UsersService;

use App\Forms\Publications\RateForm;
use App\Forms\Publications\ReadProgressForm;
use App\Validators\Publications\ReadProgressFormValidator;
use App\Validators\Publications\RateFormValidator;
use App\Cache\Publications\PublicationDeleteCache;

use App\Models\Publications\PublicationShort;
use App\Models\Enums\BookFormat;
use App\Models\Uuid;
use App\Models\Email;

use App\Exceptions\Translators\Translator as ExceptionTranslator;
use App\Lib\I18n\Translator;
use App\Lib\View;
use App\Lib\Jwt;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;
use App\ErrorCodes;

use PDO;

abstract class PublicationsService
{
    public function __construct(
        protected readonly PublicationsRepository $repository,
        protected readonly UsersReadRepository $usersReadRepo,
        protected readonly ExceptionTranslator $exceptionTranslator,
        protected readonly ReadProgressFormValidator $progressValidator,
        protected readonly RateFormValidator $rateValidator,
        protected readonly Translator $translator,
        protected readonly UnitOfWork $uow
    ){}

    // --- public

    public function retrieveShort(int $publicationId) : ?PublicationShort 
    {
        return $this->repository->retrieveShort($publicationId);
    }

    public function identifyReadContent(int $pubId, BookFormat $format = BookFormat::Pdf): ?Uuid
    {
        return $this->repository->identifyContent($pubId, $format->value);
    }

    public function getProgress(int $userId, int $pubId, BookFormat $format): ?UserRead
    {
        return $this->usersReadRepo->find($userId, $pubId, $format);
    }

    public function saveProgress(
        int $userId,
        int $pubId,
        ReadProgressForm $form
    ): void {
        $bag = $this->progressValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        $this->usersReadRepo->upsert(
            userId:        $userId,
            publicationId: $pubId,
            format:        $form->format,
            position:      $form->position,
            isFinished:    $form->isFinished,
        );
    }

    public function finishReading(
        int $userId,
        int $pubId,
        BookFormat $format,
    ): void {
        $this->usersReadRepo->markFinished(
            userId:        $userId,
            publicationId: $pubId,
            format:        $format,
        );
    }

    public function resetProgress(
        int $userId,
        int $pubId,
        ?BookFormat $format,
    ): void {
        if ($format === null) {
            $this->usersReadRepo->delete($userId, $pubId);
        } else {
            $this->usersReadRepo->deleteForFormat(
                $userId,
                $pubId,
                $format,
            );
        }
    }

    public function save(int $userId, int $publicationId) : void
    {
        try{
            $this->repository->save($userId, $publicationId);
        }
        catch(\PDOException $e)
        {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function deleteSave(int $userId, int $publicationId) : void
    {
        try{
            $this->repository->deleteSave($userId, $publicationId);
        }
        catch(\PDOException $e)
        {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function rate(int $userId, int $publicationId, RateForm $form) : void 
    {
        $bag = $this->rateValidator->validateOne($form);
        if (!$bag->isEmpty()) throw new ValidationException($bag->all());

        try{
            $this->repository->rate($userId, $publicationId, $form->rating);
        }
        catch(\PDOException $e)
        {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function unrate(int $userId, int $publicationId) : void 
    {
        try{
            $this->repository->unrate($userId, $publicationId);
        }
        catch(\PDOException $e)
        {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function publish(int $publicationId) : void 
    {
        try{
            $this->uow->transactional(function () use ($publicationId) {
                $errors = $this->repository->validatePublish($publicationId);
                if ($errors !== [])
                    throw new ConflictException($this->translator->t("errors.publications.cannot_publish") . ". " . implode("; ", $errors));

                $exists = $this->repository->publish($publicationId);
                if (!$exists) throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
            });
        }
        catch(\PDOException $e)
        {
            throw $this->exceptionTranslator->translate($e);
        }
    }

    public function delete(int $publicationId) : void 
    {
        try{
            $exists = $this->repository->delete($publicationId);
            if (!$exists) throw new NotFoundException($this->translator->t("errors.common.not_found"), ErrorCodes::NOT_FOUND);
        }
        catch(\PDOException $e)
        {
            throw $this->exceptionTranslator->translate($e);
        }
    }
}
<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\Files\UploadedFile;
use App\Validators\Validator;
use App\Lib\I18n\Translator;
use App\Lib\Settings\StorageSettings;
use App\Models\Errors\ErrorBag;
use App\Models\Enums\FileCategory;

final class FilesValidator
{
    public function __construct(
        private readonly StorageSettings $settings,
        private readonly Translator $translator,
    ){}

    public function validateOneFile(
        UploadedFile $file, 
        FileCategory $category, 
        string $label, 
        ErrorBag $bag) : bool
    {
        $allowedMimes = array_keys($this->settings->fileAllowedMimes($category));
        $maxSize = $this->settings->fileMaxSize($category);

        return $this->validateFile($file, $allowedMimes, $maxSize, $label, $bag);
    }

    public function validateManyFiles(
        array $files, 
        FileCategory $category, 
        string $label, 
        ErrorBag $bag) : bool 
    {
        $allowedMimes = array_keys($this->settings->fileAllowedMimes($category));
        $maxSize = $this->settings->fileMaxSize($category);

        $ok = true;
        for($i = 0; $i < count($files); $i++){
            $file = $files[$i];
            $ok &= $this->validateFile($file, $allowedMimes, $maxSize, $label . "[$i]", $bag);
        }
        return $ok;
    }

    private function validateFile(
        UploadedFile $file, 
        array $allowedMimes,
        int $maxSize,
        string $label,
        ErrorBag $bag) : bool
    {
        $ok = true;
        $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($file->error !== UPLOAD_ERR_OK) {
            $bag->add($label, [$this->translator->t($this->describeUploadError($error))]);
            $ok = false;
        }

        if (!is_uploaded_file($file->tmpPath)) {
            $bag->add($label, [$this->translator->t('errors.files.invalid_upload')]);
            $ok = false;
        }

        if ($file->size <= 0) {
            $bag->add($label, [$this->translator->t('errors.files.file_empty')]);
            $ok = false;
        }

        if ($file->size > $maxSize) {
            $maxMb = $maxSize / 1024 / 1024;
            $bag->add($label, [$this->translator->t('errors.files.file_large', [":max" => $maxMb])]);
            $ok = false;
        }

        return $ok;
    }

    private function describeUploadError(int $error): string
    {   
        return match ($error) {
            UPLOAD_ERR_INI_SIZE   => 'errors.files.ini_size',
            UPLOAD_ERR_FORM_SIZE  => 'errors.files.form_size',
            UPLOAD_ERR_PARTIAL    => 'errors.files.partial',
            UPLOAD_ERR_NO_FILE    => 'errors.files.no_file',
            UPLOAD_ERR_NO_TMP_DIR => 'errors.files.no_tmp_dir',
            UPLOAD_ERR_CANT_WRITE => 'errors.files.cant_write',
            UPLOAD_ERR_EXTENSION  => 'errors.files.extension',
            default               => 'errors.files.upload_fail',
        };
    }
}
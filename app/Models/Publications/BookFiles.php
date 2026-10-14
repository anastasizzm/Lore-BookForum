<?php
declare(strict_types=1);

namespace App\Models\Publications;
use App\Models\Files\UploadedFile;
use App\Extensions\UploadedFiles;

final readonly class BookFiles 
{
    public function __construct(
        public ?UploadedFile $icon,
        public array $contents
    ){}

    public static function fromInput(array $files) : self 
    {
        return new self(
            icon: UploadedFiles::one($files, 'icon'),
            contents: UploadedFiles::many($files, 'contents[]')
        );
    }
}
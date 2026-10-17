<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Forms\Publications\PublicationForm;
use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;
use App\Models\Enums\BookFormat;
use App\Forms\Form;

final readonly class BookProgressForm implements Form
{
    public function __construct(
        public BookFormat $format,
        public array $position,
        public bool $isFinished
    ){}

    public static function fromInput(array $input) : self
    {
        return new self(
            format: QueryParser::enum($input, 'format', BookFormat::class),
            position: QueryParser::assocArray($input, 'position'),
            isFinished: QueryParser::bool($input, 'isFinished')
        );
    }
}
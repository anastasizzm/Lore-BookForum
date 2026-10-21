<?php
declare(strict_types=1);

namespace App\Forms\Publications;

use App\Extensions\Parsers\QueryParser;
use App\Models\Uuid;

final readonly class RateForm
{
    public function __construct(
        public int $rating
    ){}

    public static function fromInput(array $input) : self
    {
        return new self(
            rating: QueryParser::positiveInt($input, 'rating')
        );
    }
}
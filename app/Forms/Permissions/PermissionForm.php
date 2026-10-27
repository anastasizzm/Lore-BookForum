<?php
declare(strict_types=1);

namespace App\Forms\Permissions;
use App\Forms\Form;
use App\Extensions\Parsers\QueryParser;

readonly class PermissionForm extends PositionForm
{
    public function __construct(
        public int $roleId,
        ?int $belowId,
        ?int $aboveId
    ){
        parent::__construct($belowId, $aboveId);
    }

    public static function fromInput(array $form) : self 
    {
        $parent = parent::fromInput($form);
        return new self(
            roleId : QueryParser::positiveInt($form, 'roleId'),
            belowId: $parent->belowId,
            aboveId: $parent->aboveId
        );
    }
}
<?php
declare(strict_types=1);

namespace App\Forms\Assignments;
use App\Extensions\Parsers\QueryParser;
use App\Forms\Form;

readonly class RoleToRoleUserAssignmentForm implements Form
{
    public function __construct(
        public inT $roleId
    ){}

    public static function fromInput(array $form) : self 
    {
        return new self(
            roleId: QueryParser::positiveInt($form, 'roleId')
        );
    }
}
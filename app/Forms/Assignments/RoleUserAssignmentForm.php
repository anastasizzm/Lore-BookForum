<?php
declare(strict_types=1);

namespace App\Forms\Assignments;
use App\Extensions\Parsers\QueryParser;

readonly class RoleUserAssignmentForm extends RoleToRoleUserAssignmentForm
{
    public function __construct(
        public int $userId,
        inT $roleId
    ){
        parent::__construct($roleId);
    }

    public static function fromInput(array $form) : self 
    {
        $parent = parent::fromInput($form);
        return new self(
            userId: QueryParser::positiveInt($form, 'userId'),
            roleId: $parent->roleId
        );
    }
}
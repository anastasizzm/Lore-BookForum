<?php
declare(strict_types=1);

namespace App\Forms\Assignments;
use App\Extensions\Parsers\QueryParser;
use App\Forms\Form;

readonly class RoleUserAssignmentForm implements Form
{
    public function __construct(
        public int $userId,
        public inT $roleId
    ){}

    public static function fromInput(array $form) : self 
    {
        return new self(
            userId: QueryParser::positiveInt($form, 'userId'),
            roleId: QueryParser::positiveInt($form, 'roleId')
        );
    }
}
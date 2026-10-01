<?php
declare(strict_types=1);

namespace App;

final class ErrorCodes
{
    public const INVALID_REQUIREMENT = 'invalidRequirement';
    
    public const FORBIDDEN = 'forbidden';
    public const ACCOUNT_BLOCKED = 'accountBlocked';
    public const INSUFFICIENT_PERMS = 'insufficientPermissions';

    public const CSRF_FAIL = 'csrfFail';
    public const TOKEN_FAIL = 'jwtFail';
    public const OP_FAIL = 'opFail';
    public const PATH_FAIL = 'pathFail';

    public const VALIDATION_FAIL = 'validationFail';
    public const UNHANDLED_EX = 'unhandledEx';

    public const UNAUTH_TRY = 'unauthorizedTry';
    public const ALREADY_DONE = 'alreadyDone';
}
<?php
declare(strict_types=1);

namespace App;

final class ErrorCodes
{
    public const JSON_BAD_BODY = 'json_bad_body';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';

    public const VALIDATION_FAIL = 'validation_fail';
    public const CSRF_FAIL = 'csrf_fail';
    public const TOKEN_FAIL = 'jwt_fail';
    public const OP_FAIL = 'op_fail';
    public const PATH_FAIL = 'path_fail';

    public const INVALID_REQUIREMENT = 'invalid_requirement';
    
    public const NOT_FOUND = 'not_found';
    public const UNAUTHORIZED = 'unauthorized';
    public const FORBIDDEN = 'forbidden';
    public const ACCOUNT_BLOCKED = 'accountBlocked';
    public const INSUFFICIENT_PERMS = 'insufficient_permissions';

    public const INVALID_ENCTYPTION = 'invalid_encryption';
    
    public const UNHANDLED_EX = 'unhandled';
    public const ALREADY_DONE = 'already_done';

    public const ACCEPTED = 'accepted';
}
<?php
declare(strict_types=1);

namespace App\Services;

use App\Constants;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly Jwt $jwt
    ){}

    public function login($email, $pass) : ?string
    {
        $passHash = password_hash($pass, PASSWORD_BCRYPT);
        $credits = $this->repository->findCreditsByEmail($email);
        
        if ($credits === null || $credits['is_active'] || password_verify($pass, $credits['pass_hash']))
            return null;
        
        return $this->jwt->access($credits['id'], ['username' => $credits['username'], 'csrf' => CsrfManager::generate()]);
    }
}
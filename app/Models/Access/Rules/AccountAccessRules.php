<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;
use App\Models\Access\Operations\AccessOperation;

use App\Models\Access\Operations\Account\AccountAccessOperation;
use App\Models\Access\Operations\Account\BlockAccountOperation;
use App\Models\Access\Operations\Account\ManageAccountOperation;

use App\Models\Dto;

readonly class AccountAccessRules extends Dto
{
    public function __construct(
        public bool $manage,
        public bool $block,
    ){}

    public function hasAny() : bool 
    {
        return $this->manage || $this->block;
    }

    public function allows(AccessOperation $operation) : bool
    {
        return match(true) {
            !($operation instanceof AccountAccessOperation) => false,
            $operation instanceof BlockAccountOperation => $this->block,
            $operation instanceof ManageAccountOperation => $this->manage,
            default => false,
        };
    }

    public static function fromEmpty() : self 
    {
        return new self(
            false, false
        );
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            manage: self::bool($row, $prefix . 'manage'),
            block: self::bool($row, $prefix . 'block'),
        );
    }
}
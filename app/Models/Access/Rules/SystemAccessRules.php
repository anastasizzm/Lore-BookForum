<?php
declare(strict_types=1);

namespace App\Models\Access\Rules;
use App\Models\Dto;

readonly class SystemAccessRules extends Dto
{
    public function __construct(
        public bool $adminPosts,
        public bool $adminPublications,
        public bool $adminUsers,
        public bool $adminAdditionals,
        public bool $managePeople
    ){}

    public function hasAny() : bool 
    {
        return $this->adminPosts 
            || $this->adminPublications 
            || $this->adminUsers
            || $this->adminAdditionals
            || $this->managePeople;
    }

    public static function fromRow(array $row, string $prefix = '') : self 
    {
        return new self(
            adminPosts: self::bool($row, $prefix . 'admin_posts'),
            adminPublications: self::bool($row, $prefix . 'admin_publications'),
            adminUsers: self::bool($row, $prefix . 'admin_users'),
            adminAdditionals: self::bool($row, $prefix . 'admin_additionals'),
            managePeople: self::bool($row, $prefix . 'manage_ppl')
        );
    }
}
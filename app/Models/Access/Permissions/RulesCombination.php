<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\PublicationAccessRules;

final readonly class RulesCombination 
{
    public function __construct(
        public SystemAccessRules $system,
        public PostAccessRules $post,
        public PublicationAccessRules $publication
    ){}

    public function toArray() : array 
    {
        return [
            'system' => $sysRules->toArray(),
            'post' => $postRules->toArray(),
            'publication' => $pubRules->toArray()
        ];
    }

    public static function fromEmpty() : self 
    {
        return new self(
            SystemAccessRules::fromEmpty(),
            PostAccessRules::fromEmpty(),
            PublicationAccessRules::fromEmpty(),
        );
    }

    public static function fromArray(array $data) : self 
    {
        return new self(
            system: isset($data['system']) ? SystemAccessRules::fromArray($data['system']) : SystemAccessRules::fromEmpty(),
            post: isset($data['post']) ? PostAccessRules::fromArray($data['post']) : PostAccessRules::fromEmpty(),
            publication: isset($data['publication']) ? PublicationAccessRules::fromArray($data['publication']) : PublicationAccessRules::fromEmpty(),
        );
    }
}
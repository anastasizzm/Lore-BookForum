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
            'system' => $this->system->toArray(),
            'post' => $this->post->toArray(),
            'publication' => $this->publication->toArray()
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
        $system = $data['system'];
        $post = $data['post'];
        $pub = $data['publication'];

        return new self(
            system: isset($system) ? ($system instanceof SystemAccessRules ? $system : SystemAccessRules::fromArray($system)) : SystemAccessRules::fromEmpty(),
            post: isset($post) ? ($post instanceof PostAccessRules ? $post : PostAccessRules::fromArray($post)) : PostAccessRules::fromEmpty(),
            publication: isset($publication) ? ($publication instanceof PublicationAccessRules ? $publication : PublicationAccessRules::fromArray($publication)) : PublicationAccessRules::fromEmpty(),
        );
    }
}
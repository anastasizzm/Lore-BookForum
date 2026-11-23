<?php
declare(strict_types=1);

namespace App\Models\Access\Permissions;
use App\Models\Access\Rules\SystemAccessRules;
use App\Models\Access\Rules\PostAccessRules;
use App\Models\Access\Rules\SystemPublicationAccessRules;

final readonly class RulesCombination
{
    public function __construct(
        public SystemAccessRules $system,
        public PostAccessRules $post,
        public SystemPublicationAccessRules $publication
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
            SystemPublicationAccessRules::fromEmpty(),
        );
    }

    public static function fromArray(array $data) : self
    {
        $system = $data['system'] ?? null;
        $post = $data['post'] ?? null;
        $pub = $data['publication'] ?? null;

        return new self(
            system: isset($system) ? ($system instanceof SystemAccessRules ? $system : SystemAccessRules::fromArray($system)) : SystemAccessRules::fromEmpty(),
            post: isset($post) ? ($post instanceof PostAccessRules ? $post : PostAccessRules::fromArray($post)) : PostAccessRules::fromEmpty(),
            publication: isset($pub) ? ($pub instanceof SystemPublicationAccessRules ? $pub : SystemPublicationAccessRules::fromArray($pub)) : SystemPublicationAccessRules::fromEmpty(),
        );
    }
}

<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Publications;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\PostsSortBy;

final class PostsScriptDirector extends ScriptDirector
{
    public function __construct()
    {
        parent::__construct("comments");
    }

    public function getExistsScript(int $commentId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("id = :commentId", [':commentId' => ScriptParam::asInt($commentId)]);
        return $this->builder->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('comments');
        $this->tempBuilder->addWhere('comments.is_active')
            ->addJoin('INNER JOIN users ON users.id = comments.creator_id');
        return $this;
    }

    public function addPostSelectTemp() : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("comments.id,\ncomments.content,\ncomments.is_active,\ncomments.creator_id,\ncomments.publication_id,\ncomments.created_at,\ncomments.likes_count,\ncomments.comments_count");
        return $this;
    }

    public function addConcreteTempFilter(int $commentId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("comments.id = :commentId", [':commentId' => ScriptParam::asInt($commentId)]);
        return $this;
    }

    public function addParentTempFilter(?int $parentId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        if ($parentId === null) $this->tempBuilder->addWhere("comments.parent_id IS NULL");
        else $this->tempBuilder->addWhere("comments.parent_id = :parentId", [':parentId' => ScriptParam::asInt($parentId)]);
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("users.username ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addCreatorTempFilter(int $creatorId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("comments.creator_id = :creatorId", [':creatorId' => ScriptParam::asInt($creatorId)]);
        return $this;
    }

    public function addPublicationTempFilter(int $publicationId) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("comments.publication_id = :publicationId", [':publicationId' => ScriptParam::asInt($publicationId)]);
        return $this;
    }

    public function addOrderTemp(PostsSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            PostsSortBy::Popularity => 'comments.likes_count DESC, comments.comments_count DESC',
            PostsSortBy::Newest => 'comments.created_at DESC',
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function addIncludesTemp(array $includeObjects) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        foreach($includeObjects as $prop)
        {
            $prop = strtolower($prop);
            switch($prop)
            {
                case 'creator':
                    $this->tempBuilder->addJoin('INNER JOIN profiles ON profiles.user_id = users.id')
                        ->addSelect("users.id as u_id,\nusers.username as u_username")
                        ->addSelect("profiles.name as u_name,\nprofiles.surname as u_surname,\nprofiles.avatar as u_avatar");
                    break;
                
                case 'publication':
                    $this->tempBuilder->addJoin('INNER JOIN publications ON publications.id = comments.publication_id')
                        ->addSelect("publications.id as pub_id,\npublications.title as pub_title,\npublications.icon_id as pub_icon_id,\npublications.created_at as pub_created_at");
                    break;
                default:
                    $this->handleFallbackIncluding($prop);
                    break;
            }
        }

        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}
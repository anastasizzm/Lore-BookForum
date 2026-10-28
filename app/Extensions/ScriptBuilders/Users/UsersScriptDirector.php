<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Users;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\UsersSortBy;

final class UsersScriptDirector extends ScriptDirector
{
    public function __construct()
    {
        parent::__construct("users");
    }

    public function getExistsScript(int $userId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("id = :userId", [':userId' => ScriptParam::asInt($userId)]);
        return $this->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('users');
        $this->tempBuilder->addJoin("INNER JOIN profiles ON profiles.user_id = users.id");
        return $this;
    }

    public function addUserShortSelectTemp() : self 
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("users.id,\nusers.username,\nprofiles.name,\nprofiles.surname,\nprofiles.avatar");
        return $this;
    }

    public function addUserSelectTemp() : self 
    {
        $this->addUserShortSelectTemp();
        $this->tempBuilder->addSelect("users.email,\nprofiles.bio,\nusers.created_at,\nusers.deleted_at");
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("users.username ILIKE :q OR (COALESCE(profiles.name, '') || ' ' || COALESCE(profiles.surname, '')) ILIKE :q", 
            [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addConcreteTempFilter(int $id) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("users.id = :id", [':id' => ScriptParam::asInt($id)]);
        return $this;
    }

    public function addOrderTemp(UsersSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            UsersSortBy::Username => 'users.username',
            UsersSortBy::Name => 'profiles.name, profiles.surname',
            UsersSortBy::Created => 'users.created_at DESC',
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function addActiveTempFilter() : self 
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("users.is_active");
        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}
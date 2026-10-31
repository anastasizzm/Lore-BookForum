<?php
declare(strict_types=1);

namespace App\Extensions\ScriptBuilders\Additional;

use App\Extensions\ScriptBuilders\ScriptBuilder;
use App\Extensions\ScriptBuilders\ScriptDirector;

use App\Models\Scripts\ScriptData;
use App\Models\Scripts\ScriptParam;

use App\Models\Enums\BasicModelSortBy;
use App\Models\Enums\RoleStatus;

final class RolesScriptDirector extends ScriptDirector
{
    public function __construct()
    {
        parent::__construct("user_roles");
    }

    public function getExistsScript(int $roleId) : ScriptData
    {
        $this->reset();
        $this->builder
            ->addSelect("1")
            ->addWhere("id = :roleId", [':roleId' => ScriptParam::asInt($roleId)]);
        return $this->build();
    }

    public function startTempFilter() : self
    {
        $this->startTemp('user_roles');
        $this->tempBuilder->addJoin("INNER JOIN user_roles_translations ON user_roles_translations.role_id = user_roles.id")
            ->addJoin("INNER JOIN languages ON languages.code = user_roles_translations.code AND languages.is_active");
        return $this;
    }

    public function addRoleSelectTemp(string $currentLocale, string $fallbackLocale) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addSelect("user_roles.id,\nuser_roles.color,\nuser_roles.is_system,\nuser_roles.created_at,\nuser_roles.deleted_at")
            ->addSelect("COALESCE(
                    MAX(CASE WHEN user_roles_translations.code = :currentCulture THEN user_roles_translations.title END),
                    MAX(CASE WHEN user_roles_translations.code = :fallbackCulture THEN user_roles_translations.title END)
                ) as title", [':currentCulture' => ScriptParam::asStr($currentLocale), ':fallbackCulture' => ScriptParam::asStr($fallbackLocale)])
            ->setGroup("user_roles.id");
        
        return $this;
    }

    public function addStatusTempFilter(RoleStatus $status) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $query = match($status){
            RoleStatus::Public => 'user_roles.is_public',
            RoleStatus::Private => 'NOT user_roles.is_public',
            RoleStatus::All => '',
        };
        if (!empty($query)) $this->tempBuilder->addWhere($order);
        return $this;
    }

    public function addConcreteTempFilter(int $id) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("user_roles.id = :id", [':id' => ScriptParam::asInt($id)]);
        return $this;
    }

    public function addSearchTempFilter(string $search) : self
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("user_roles_translations.title ILIKE :q", [':q' => ScriptParam::asStr('%' .$search . '%')]);
        return $this;
    }

    public function addOrderTemp(BasicModelSortBy $sort)
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $order = match($sort){
            BasicModelSortBy::Alphabet => 'title',
            BasicModelSortBy::Newest => 'user_roles.created_at DESC',
        };
        $this->tempBuilder->setOrder($order);
        return $this;
    }

    public function addActiveTempFilter() : self 
    {
        if (!$this->isTempStarted()) $this->startTempFilter();
        $this->tempBuilder->addWhere("user_roles.is_active");
        return $this;
    }

    public function buildTempFilter() : ScriptData
    {
        return $this->buildTemp();
    }
}
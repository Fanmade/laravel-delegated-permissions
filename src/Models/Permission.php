<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Models;

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class Permission extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return DelegatedPermissions::table('permissions');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, DelegatedPermissions::table('permission_role'), 'permission_id', 'role_id');
    }

    /**
     * @return BelongsToMany<PermissionGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PermissionGroup::class, DelegatedPermissions::table('permission_group_permission'), 'permission_id', 'permission_group_id');
    }
}

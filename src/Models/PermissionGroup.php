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
class PermissionGroup extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return DelegatedPermissions::table('permission_groups');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, DelegatedPermissions::table('permission_group_permission'), 'permission_group_id', 'permission_id');
    }
}

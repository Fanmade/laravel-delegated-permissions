<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Models;

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $description
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property bool $is_system
 */
class Role extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return DelegatedPermissions::table('roles');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, DelegatedPermissions::table('permission_role'), 'role_id', 'permission_id');
    }

    /**
     * The model this role is scoped to (e.g. a Project), or null when global.
     *
     * @return MorphTo<Model, $this>
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}

<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Tests\Fixtures;

use Fanmade\DelegatedPermissions\Concerns\HasRoles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * A minimal authorizable for the test suite.
 *
 * @property int $id
 * @property string|null $name
 */
class User extends Model
{
    use Authorizable;
    use HasRoles;

    protected $guarded = [];
}

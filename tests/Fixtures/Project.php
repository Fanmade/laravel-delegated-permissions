<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A minimal scope target for the test suite.
 *
 * @property int $id
 * @property string|null $name
 */
class Project extends Model
{
    protected $guarded = [];
}

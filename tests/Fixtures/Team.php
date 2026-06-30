<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A scope target with a non-integer (string) primary key, to prove scope
 * resolution does not collapse distinct keys via an integer cast.
 *
 * @property string $id
 * @property string|null $name
 */
class Team extends Model
{
    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RoleUser extends Pivot
{
    use HasUuids;

    protected $table = 'role_user';

    public $incrementing = false;
    protected $keyType = 'string';
}

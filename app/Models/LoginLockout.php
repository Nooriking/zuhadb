<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLockout extends Model
{
    protected $table = 'login_lockouts';

    protected $fillable = [
        'lock_key',
        'attempts',
        'locked_until',
        'ip',
        'login',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'locked_until' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MashpiaRoleMap extends Model
{
    protected $fillable = ['mashpia_role', 'admin_role', 'can_request_admin'];

    protected $casts = ['can_request_admin' => 'boolean'];
}

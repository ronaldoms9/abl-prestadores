<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Trebol\Entrust\EntrustPermission;

class Permission extends Model
{
    use HasFactory;

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }
}

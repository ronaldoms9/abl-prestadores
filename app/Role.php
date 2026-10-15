<?php

namespace App;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    protected $fillable = [
        'display_name', 'name', 'description'
    ];




    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users(){
        return $this->belongsToMany(User::class, 'role_user');
    }
}

<?php

namespace App;

use App\Models\Concerns\Auditavel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use Auditavel, HasFactory;

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

    protected function prefixoAuditoria(): string
    {
        return 'perfil';
    }

    protected function camposAuditados(): array
    {
        return ['display_name', 'description'];
    }
}

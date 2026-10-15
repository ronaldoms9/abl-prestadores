<?php

namespace App\Models;

use App\Permission;
use App\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** @var array<int, string>|null */
    protected ?array $permissionNamesCache = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telefone',
        'cpf',
        'photo',
        'photo_url',
        'photo_url_expire'

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'photo_url_expire'
    ];

    protected $appends = [
        'photo_url'
    ];

    protected function getPhotoUrlAttribute()
    {
        if($this->getAttribute('photo') &&  Storage::disk('public')->exists($this->getAttribute('photo'))){
                    return url("storage/".$this->getAttribute('photo'));
        }
        return asset('images/avatar.png');
//        return 'https://i.pinimg.com/736x/5a/72/e1/5a72e1f05f9e2e1b76a8438a7490dc3b.jpg';
    }



    /**
     * Sobrescreve o can() do Laravel para trabalhar com nomes de permissão.
     *
     * As permissões são memoizadas por instância: o comportamento antigo fazia
     * uma consulta por chamada, inclusive dentro de laços em views.
     */
    public function can($permissions, $full = false)
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $encontradas = array_unique(array_intersect($permissions, $this->permissionNames()));

        return $full
            ? count($encontradas) === count(array_unique($permissions))
            : count($encontradas) > 0;
    }

    /** Nomes de todas as permissões do usuário, carregados uma vez por instância. */
    public function permissionNames(): array
    {
        return $this->permissionNamesCache ??= Permission::query()
            ->whereHas('roles.users', fn ($w) => $w->where('users.id', $this->getKey()))
            ->pluck('name')
            ->all();
    }

    public function esquecerPermissoes(): void
    {
        $this->permissionNamesCache = null;
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function firstName()
    {
        $fName = explode(' ', $this->getAttribute('name'));
        return $fName[0] ?? '';
    }
}

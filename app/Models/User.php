<?php

namespace App\Models;

use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Notifications\RedefinirSenha;
use App\Permission;
use App\Role;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Ativavel, Auditavel, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

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
        'photo_url_expire',
        'ativo',
        'pessoa_fisica_id',
    ];

    /** Valores padrão em memória, iguais aos da tabela. */
    protected $attributes = [
        'ativo' => true,
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
        'ativo' => 'boolean',
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
     * Aceita nomes de permissão além das abilities do Gate.
     *
     * - can('x') / can(['a', 'b']): tem ao menos uma das permissões;
     * - can(['a', 'b'], true): tem todas;
     * - ability definida no Gate ou chamada com argumentos (Policy, como
     *   can('update', $pessoa)): segue para o Gate do Laravel.
     *
     * As permissões são memoizadas por instância.
     */
    public function can($abilities, $arguments = [])
    {
        if (! is_bool($arguments) && ($arguments !== [] || Gate::has($abilities))) {
            return parent::can($abilities, $arguments);
        }

        $permissions = is_array($abilities) ? $abilities : [$abilities];

        $encontradas = array_unique(array_intersect($permissions, $this->permissionNames()));

        return $arguments === true
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

    protected function prefixoAuditoria(): string
    {
        return 'usuario';
    }

    protected function camposAuditados(): array
    {
        return ['name', 'email', 'cpf', 'telefone', 'ativo'];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RedefinirSenha($token));
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

    public function pessoaFisica(): BelongsTo
    {
        return $this->belongsTo(PessoaFisica::class, 'pessoa_fisica_id');
    }
}

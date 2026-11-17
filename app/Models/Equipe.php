<?php

namespace App\Models;

use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipe extends Model
{
    use Ativavel, Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'equipes';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function gestores(): HasMany
    {
        return $this->hasMany(EquipeGestor::class, 'equipe_id');
    }

    public function vinculosColaborador(): HasMany
    {
        return $this->hasMany(ColaboradorEquipe::class, 'equipe_id');
    }

    /** Usuários que são gestores da equipe no dia (README D7). */
    public function gestoresVigentesEm(CarbonInterface|string $data): Builder
    {
        return User::query()->whereIn('users.id', EquipeGestor::query()
            ->where('equipe_id', $this->id)
            ->vigentesEm($data)
            ->select('user_id'));
    }

    /**
     * Equipes que o usuário enxerga: todas, para quem edita equipes ou não
     * gere nenhuma; só as que gere hoje, para o gestor sem permissão de editar.
     */
    public function scopeVisiveisPara(Builder $query, User $usuario): Builder
    {
        if ($usuario->can('equipe-edit') || $usuario->can('equipe-create') || ! $usuario->can('equipe-gerir')) {
            return $query;
        }

        return $query->whereIn('equipes.id', EquipeGestor::query()
            ->where('user_id', $usuario->id)
            ->vigentesEm(now())
            ->select('equipe_id'));
    }

    protected function prefixoAuditoria(): string
    {
        return 'equipe';
    }

    protected function camposAuditados(): array
    {
        return ['nome', 'descricao', 'ativo'];
    }
}

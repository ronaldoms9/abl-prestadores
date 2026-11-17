<?php

namespace App\Models;

use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Item configurável do checklist de admissão. Com `chave`, o sistema marca sozinho. */
class AdmissaoChecklistItem extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'admissao_checklist_itens';

    protected $fillable = [
        'nome',
        'chave',
        'obrigatorio',
        'ordem',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'ordem' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function automatico(): bool
    {
        return $this->chave !== null;
    }
}

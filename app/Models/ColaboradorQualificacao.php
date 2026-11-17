<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ColaboradorQualificacao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'colaborador_qualificacoes';

    protected $fillable = [
        'colaborador_id',
        'qualificacao_tipo_id',
        'data_conclusao',
        'data_validade',
        'certificado_path',
        'instituicao',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'data_conclusao' => 'date',
            'data_validade' => 'date',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(QualificacaoTipo::class, 'qualificacao_tipo_id');
    }
}

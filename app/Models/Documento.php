<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'documentos';

    protected $fillable = [
        'tipo',
        'arquivo_path',
        'validade',
        'observacao',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'validade' => 'date',
        ];
    }

    public function documentavel(): MorphTo
    {
        return $this->morphTo();
    }
}

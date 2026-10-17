<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UauMapeamento extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'uau_mapeamentos';

    protected $fillable = [
        'tipo',
        'chave_local',
        'codigo_uau',
    ];
}

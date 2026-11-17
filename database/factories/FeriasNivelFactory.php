<?php

namespace Database\Factories;

use App\Enums\TipoAprovadorFerias;
use App\Models\FeriasNivel;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeriasNivelFactory extends Factory
{
    protected $model = FeriasNivel::class;

    public function definition(): array
    {
        return [
            'ordem' => 1,
            'tipo_aprovador' => TipoAprovadorFerias::GestorEquipe,
            'permission_id' => null,
            'ativo' => true,
        ];
    }
}

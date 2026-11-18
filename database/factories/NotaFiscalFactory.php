<?php

namespace Database\Factories;

use App\Enums\OrigemDadosNota;
use App\Enums\StatusNota;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotaFiscalFactory extends Factory
{
    protected $model = NotaFiscal::class;

    public function definition(): array
    {
        return [
            'fechamento_id' => Fechamento::factory(),
            'colaborador_id' => fn (array $a) => Fechamento::find($a['fechamento_id'])->colaborador_id,
            'competencia_id' => fn (array $a) => Fechamento::find($a['fechamento_id'])->competencia_id,
            'numero' => (string) fake()->unique()->numberBetween(1, 99999),
            'serie' => '1',
            'chave_acesso' => null,
            'data_emissao' => '2026-10-02',
            'cnpj_emissor' => '12345678000195',
            'cnpj_tomador' => '45723174000110',
            'valor_bruto' => 5300,
            'valor_retencoes' => 0,
            'valor_liquido' => 5300,
            'status' => StatusNota::Enviada,
            'origem_dados' => OrigemDadosNota::Xml,
        ];
    }
}

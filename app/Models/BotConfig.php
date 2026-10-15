<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BotConfig extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nome_negocio',
        'descricao',
        'tom',
        'horarios',
        'produtos',
        'faqs',
        'nunca_faca',
        'quando_humano',
        'saudacao',
    ];

    protected $casts = [
        'horarios' => 'array',
        'produtos' => 'array',
        'faqs' => 'array',
    ];

    /** @return array<int, array{nome: string, preco: float|null, descricao: string|null}> */
    public function produtosNormalizados(): array
    {
        return array_map(fn (array $p) => [
            'nome' => (string) ($p['nome'] ?? ''),
            'preco' => isset($p['preco']) && is_numeric($p['preco']) ? (float) $p['preco'] : null,
            'descricao' => $p['descricao'] ?? null,
        ], $this->produtos ?? []);
    }

    /**
     * O bot atende 24 horas — isso é o produto. Saber que a mensagem chegou
     * fora do horário serve para a métrica e para o texto da resposta.
     */
    public function estaForaDeHorario(?\Carbon\CarbonInterface $momento = null): bool
    {
        $horarios = $this->horarios ?? [];

        if ($horarios === []) {
            return false;
        }

        $momento ??= now();
        $dias = ['domingo', 'segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado'];
        $dia = $dias[(int) $momento->dayOfWeek];

        $faixa = null;

        foreach ($horarios as $chave => $valor) {
            if ($this->semAcento((string) $chave) === $dia) {
                $faixa = $valor;
                break;
            }
        }

        if ($faixa === null || $faixa === false || $faixa === 'fechado') {
            return true;
        }

        if (! is_array($faixa) || ! isset($faixa['abre'], $faixa['fecha'])) {
            return false;
        }

        $agora = $momento->format('H:i');

        return $agora < $faixa['abre'] || $agora >= $faixa['fecha'];
    }

    private function semAcento(string $texto): string
    {
        $de = ['á', 'â', 'ã', 'à', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç'];
        $para = ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c'];

        return str_replace($de, $para, mb_strtolower($texto));
    }

    /** Todos os preços que o bot tem permissão de dizer. */
    public function precosPermitidos(): array
    {
        return array_values(array_filter(
            array_column($this->produtosNormalizados(), 'preco'),
            fn ($preco) => $preco !== null
        ));
    }
}

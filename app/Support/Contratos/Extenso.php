<?php

namespace App\Support\Contratos;

use Carbon\CarbonInterface;

/** Valor e data por extenso, em português, para os contratos. */
class Extenso
{
    private const UNIDADES = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];

    private const DEZENAS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

    private const CENTENAS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    private const MESES = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    /** 150050 centavos viram "mil e quinhentos reais e cinquenta centavos". */
    public static function moeda(int $centavos): string
    {
        if ($centavos < 0) {
            return 'menos '.self::moeda(-$centavos);
        }

        $reais = intdiv($centavos, 100);
        $centavosRestantes = $centavos % 100;

        $partes = [];
        if ($reais > 0 || $centavosRestantes === 0) {
            $texto = self::inteiro($reais);
            // "um milhão de reais", "dois bilhões de reais": o "de" aparece quando não sobra nada abaixo de um milhão.
            $deReais = $reais >= 1_000_000 && $reais % 1_000_000 === 0 ? ' de' : '';
            $partes[] = $texto.$deReais.($reais === 1 ? ' real' : ' reais');
        }

        if ($centavosRestantes > 0) {
            $partes[] = self::inteiro($centavosRestantes).($centavosRestantes === 1 ? ' centavo' : ' centavos');
        }

        return implode(' e ', $partes);
    }

    /** 1234 vira "mil duzentos e trinta e quatro". 0 vira "zero". */
    public static function inteiro(int $numero): string
    {
        if ($numero === 0) {
            return 'zero';
        }

        // [grupo de três dígitos, posição]: 3 bilhão, 2 milhão, 1 mil, 0 unidades. Só os grupos não nulos.
        $grupos = [];
        for ($posicao = 3; $posicao >= 0; $posicao--) {
            $grupo = intdiv($numero, 1000 ** $posicao);
            $grupo = $posicao === 3 ? $grupo : $grupo % 1000;
            if ($grupo > 0) {
                $grupos[] = [$grupo, $posicao];
            }
        }

        $texto = '';
        foreach ($grupos as $indice => [$grupo, $posicao]) {
            $texto .= self::grupo($grupo, $posicao);

            if (isset($grupos[$indice + 1])) {
                $texto .= self::ligacao(array_slice($grupos, $indice + 1));
            }
        }

        return $texto;
    }

    /** "17 de novembro de 2026". */
    public static function data(CarbonInterface $data): string
    {
        return $data->day.' de '.self::MESES[$data->month].' de '.$data->year;
    }

    /** Menor que mil. */
    private static function centena(int $numero): string
    {
        if ($numero === 100) {
            return 'cem';
        }

        $partes = [];
        if ($numero >= 100) {
            $partes[] = self::CENTENAS[intdiv($numero, 100)];
            $numero %= 100;
        }

        if ($numero > 0) {
            $partes[] = $numero < 20
                ? self::UNIDADES[$numero]
                : self::DEZENAS[intdiv($numero, 10)].($numero % 10 > 0 ? ' e '.self::UNIDADES[$numero % 10] : '');
        }

        return implode(' e ', $partes);
    }

    private static function grupo(int $valor, int $posicao): string
    {
        return match ($posicao) {
            0 => self::centena($valor),
            1 => $valor === 1 ? 'mil' : self::centena($valor).' mil',
            2 => self::centena($valor).($valor === 1 ? ' milhão' : ' milhões'),
            default => self::centena($valor).($valor === 1 ? ' bilhão' : ' bilhões'),
        };
    }

    /**
     * Entre um grupo e o que vem depois: " e " quando o resto é um só grupo,
     * menor que cem ou centena exata ("mil e quinhentos", "um milhão e doze
     * mil", "dois mil e vinte"); espaço nos demais ("mil duzentos e trinta").
     *
     * @param  array<int, array{0: int, 1: int}>  $resto
     */
    private static function ligacao(array $resto): string
    {
        if (count($resto) !== 1) {
            return ' ';
        }

        $grupo = $resto[0][0];

        return ($grupo < 100 || $grupo % 100 === 0) ? ' e ' : ' ';
    }
}

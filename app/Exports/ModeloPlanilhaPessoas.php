<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Modelo da carga de pessoas: colunas cpf e cnpj no formato texto, para o
 * Excel não apagar o zero à esquerda (D5b.3 da fatia 5b).
 */
class ModeloPlanilhaPessoas implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings
{
    public function headings(): array
    {
        return ['cpf', 'cnpj'];
    }

    public function array(): array
    {
        return [];
    }

    public function columnFormats(): array
    {
        // Sem linhas de dados, "A" formataria só o cabeçalho: vai até o limite da carga.
        $ultima = (int) config('uau.planilha.max_linhas', 2000) + 1;

        return [
            "A1:A{$ultima}" => NumberFormat::FORMAT_TEXT,
            "B1:B{$ultima}" => NumberFormat::FORMAT_TEXT,
        ];
    }
}

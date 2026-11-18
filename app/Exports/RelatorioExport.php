<?php

namespace App\Exports;

use App\Services\Relatorios\ResultadoRelatorio;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Excel de um relatório: cabeçalho, as mesmas linhas da tela e o total. */
class RelatorioExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(private readonly string $titulo, private readonly ResultadoRelatorio $resultado)
    {
    }

    public function headings(): array
    {
        return array_column($this->resultado->colunas, 'rotulo');
    }

    public function array(): array
    {
        return $this->resultado->matriz();
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $this->titulo), 0, 31);
    }
}

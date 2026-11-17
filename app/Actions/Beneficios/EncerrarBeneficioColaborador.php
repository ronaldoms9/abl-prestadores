<?php

namespace App\Actions\Beneficios;

use App\Models\ColaboradorBeneficio;
use App\Support\Auditoria;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Encerra o benefício do colaborador na data informada (fim exclusivo: vale até a véspera). */
class EncerrarBeneficioColaborador
{
    public function __construct(private readonly CompetenciaFechada $competencias)
    {
    }

    public function executar(ColaboradorBeneficio $vinculo, ?CarbonInterface $fim = null): void
    {
        $fim = ($fim ?? now())->copy()->startOfDay();

        if ($vinculo->fim !== null) {
            throw ValidationException::withMessages(['fim' => 'Este benefício já foi encerrado.']);
        }

        if ($fim->lte($vinculo->inicio)) {
            throw ValidationException::withMessages(['fim' => 'O fim precisa ser posterior ao início ('.$vinculo->inicio->format('d/m/Y').').']);
        }

        if ($this->competencias->fechada($fim)) {
            throw ValidationException::withMessages(['fim' => 'A competência de '.$fim->format('m/Y').' já está fechada. Encerramento retroativo não é permitido.']);
        }

        $vinculo->update(['fim' => $fim]);

        Auditoria::registrar('colaborador-beneficio-encerrado', $vinculo->colaborador, ['beneficio' => $vinculo->beneficio->nome, 'fim' => $fim->toDateString()]);
    }
}

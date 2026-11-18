<?php

namespace App\Console\Commands;

use App\Enums\StatusFechamento;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\Fechamento;
use App\Models\User;
use App\Notifications\NotaNaoEnviada;
use App\Notifications\ResumoNotasPendentes;
use App\Support\Alertas\DestinatariosAlerta;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Alerta diário de notas atrasadas. O prazo é o dia `notas.prazo_dia` (padrão 5)
 * do mês seguinte à competência; passado o prazo, quem ainda não tem nota válida
 * (nunca enviou, ou foi recusada) recebe um lembrete e o financeiro, um resumo.
 */
class AlertasNotas extends Command
{
    public const PERMISSAO = 'alerta-notas';

    protected $signature = 'alertas:notas';

    protected $description = 'Avisa colaboradores e o financeiro das notas fiscais que passaram do prazo';

    public function handle(): int
    {
        $hoje = now()->startOfDay();
        $dia = (int) Configuracao::obter('notas.prazo_dia', config('cadastros.notas.prazo_dia'));
        $grupos = collect();
        $lembretes = 0;

        foreach (Competencia::query()->whereIn('status', ['aprovada', 'fechada'])->orderBy('ano_mes')->get() as $competencia) {
            $mes = Carbon::createFromFormat('Y-m-d', $competencia->ano_mes.'-01')->startOfDay()->addMonth();
            $prazo = $mes->copy()->day(min($dia, $mes->daysInMonth));

            if (! $hoje->gt($prazo)) {
                continue;
            }

            $atrasados = Fechamento::query()
                ->with(['colaborador.pessoaFisica', 'nota'])
                ->where('competencia_id', $competencia->id)
                ->where('status', StatusFechamento::AguardandoNota->value)
                ->get()
                ->filter(fn (Fechamento $f) => $f->nota === null || $f->nota->status->aceitaEnvio());

            if ($atrasados->isEmpty()) {
                continue;
            }

            foreach ($atrasados as $f) {
                User::query()->ativos()->where('pessoa_fisica_id', $f->colaborador->pessoa_fisica_id)->get()
                    ->each(function (User $u) use ($competencia, $prazo, &$lembretes) {
                        $u->notify(new NotaNaoEnviada($competencia, $prazo));
                        $lembretes++;
                    });
            }

            $grupos->push(['competencia' => $competencia, 'nomes' => $atrasados->map(fn ($f) => $f->colaborador->pessoaFisica->nome)->sort()->values()->all()]);
        }

        $destinatarios = DestinatariosAlerta::comPermissao(self::PERMISSAO, 'alerta de notas fiscais atrasadas');
        if ($destinatarios->isNotEmpty() && $grupos->isNotEmpty()) {
            Notification::send($destinatarios, new ResumoNotasPendentes($grupos));
        }

        $this->info(sprintf('%d lembrete(s) a colaboradores; %d competência(s) em atraso.', $lembretes, $grupos->count()));

        return self::SUCCESS;
    }
}

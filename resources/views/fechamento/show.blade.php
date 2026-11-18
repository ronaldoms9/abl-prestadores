@extends('layouts.app', ['title' => 'Fechamento '.substr($competencia->ano_mes, 5, 2).'/'.substr($competencia->ano_mes, 0, 4)])

@php
    $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $cores = ['aberta' => 'secondary', 'em_conferencia' => 'warning', 'aprovada' => 'success', 'fechada' => 'dark'];
    $editavel = ! $competencia->status->imutavel();
@endphp

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow mb-3">
        <div class="card-header border-0 d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0 me-auto">Fechamento de {{ substr($competencia->ano_mes, 5, 2) }}/{{ substr($competencia->ano_mes, 0, 4) }}
                <span class="badge text-bg-{{ $cores[$competencia->status->value] }}">{{ $competencia->status->rotulo() }}</span></h5>
            <a href="{{ route('fechamento.index') }}" class="btn btn-link btn-sm">Voltar</a>
            @if ($editavel)
                @permission('fechamento-conferir')
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-acao="{{ route('fechamento.recalcular-todos', $competencia->id) }}" data-confirma="Recalcular todos os colaboradores? Os ajustes avulsos são mantidos.">Recalcular todos</button>
                @endpermission
                @permission('fechamento-aprovar')
                    @if ($competencia->status === \App\Enums\StatusCompetencia::EmConferencia && $fechamentos->isNotEmpty())
                        <button type="button" class="btn btn-success btn-sm" id="btn-aprovar" data-url="{{ route('fechamento.aprovar', $competencia->id) }}" data-avisos="{{ $comAviso }}">Aprovar competência</button>
                    @endif
                @endpermission
            @else
                @permission('fechamento-reabrir')
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btn-reabrir" data-url="{{ route('fechamento.reabrir', $competencia->id) }}">Reabrir</button>
                @endpermission
            @endif
        </div>
        <div class="card-body">
            @if ($competencia->status === \App\Enums\StatusCompetencia::Aberta)
                <div class="alert alert-info py-2 small">O cálculo está rodando em segundo plano. Atualize a tela em instantes.</div>
            @elseif ($editavel)
                <div class="alert alert-secondary py-2 small">Férias, faltas, feriados e benefícios lançados depois do cálculo só entram quando o colaborador (ou todos) for recalculado.</div>
            @else
                <p class="small text-muted">Aprovada por {{ $competencia->aprovador?->name ?? '—' }} em {{ $competencia->aprovada_em?->format('d/m/Y H:i') }}. Os valores do mês não mudam mais.</p>
            @endif
            @if ($comAviso > 0)
                <div class="alert alert-warning py-2 small">{{ $comAviso }} colaborador(es) com aviso de cálculo (remuneração ou valor de benefício faltando). Abra o detalhe para ver.</div>
            @endif
            <div class="row text-center mb-3">
                @foreach (['Remuneração' => 'remuneracao', 'Benefícios' => 'beneficios', 'Ajustes' => 'ajustes', 'Total líquido' => 'liquido'] as $rotulo => $chave)
                    <div class="col-6 col-md-3"><div class="text-muted small">{{ $rotulo }}</div><div class="h5 mb-0">{{ $fmt($totais[$chave]) }}</div></div>
                @endforeach
            </div>
            @if ($fechamentos->isEmpty())
                <p class="text-muted mb-0">Nenhum colaborador calculado.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Colaborador</th><th class="text-end">Dias</th><th class="text-end">Trabalhados</th><th class="text-end">Remuneração</th><th class="text-end">Benefícios</th><th class="text-end">Ajustes</th><th class="text-end">Líquido</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($fechamentos as $f)
                            <tr>
                                <td>{{ $f->colaborador->pessoaFisica->nome }}@if (! empty($f->snapshot['avisos'] ?? [])) <span class="badge text-bg-warning" title="{{ implode(' ', $f->snapshot['avisos']) }}">aviso</span>@endif</td>
                                <td class="text-end">{{ $f->dias_periodo }}</td>
                                <td class="text-end">{{ (int) $f->dias_trabalhados }}</td>
                                <td class="text-end">{{ $fmt($f->valor_remuneracao) }}</td>
                                <td class="text-end">{{ $fmt($f->total_beneficios) }}</td>
                                <td class="text-end">{{ $fmt($f->total_ajustes) }}</td>
                                <td class="text-end"><strong>{{ $fmt($f->total_liquido) }}</strong></td>
                                <td class="text-end"><a href="javascript:requestIntoModal('{{ route('fechamento.detalhe', $f->id) }}')" class="btn btn-outline-primary btn-sm">Detalhe</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    (() => {
        const ok = (r) => { showNotify('Sucesso', r.data.message); setTimeout(() => location.reload(), 900); };
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        document.querySelectorAll('[data-acao]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm(b.dataset.confirma)) return;
            axios.post(b.dataset.acao, {}).then(ok).catch(falha);
        }));
        document.getElementById('btn-aprovar')?.addEventListener('click', function () {
            const avisos = parseInt(this.dataset.avisos, 10);
            const msg = avisos > 0
                ? avisos + ' colaborador(es) têm aviso de cálculo. Aprovar mesmo assim? Depois de aprovada, a competência não muda mais.'
                : 'Aprovar a competência? Depois de aprovada, os valores do mês não mudam mais.';
            if (!confirm(msg)) return;
            axios.post(this.dataset.url, { ignorar_avisos: avisos > 0 }).then(ok).catch(falha);
        });
        document.getElementById('btn-reabrir')?.addEventListener('click', function () {
            const justificativa = prompt('Por que a competência está sendo reaberta?');
            if (!justificativa) return;
            axios.post(this.dataset.url, { justificativa }).then(ok).catch(falha);
        });
    })();
</script>
@endpush

{{-- Períodos aquisitivos e saldo de férias (E4, fatia 2). Espera $colaborador com feriasPeriodos. --}}
@php $periodos = $colaborador->feriasPeriodos; $hoje = now()->startOfDay(); @endphp
<h6 class="text-semibold mt-3">Férias</h6>
@if ($periodos->isEmpty())
    <p class="text-muted">
        @if ($colaborador->status === \App\Enums\StatusColaborador::Ativo)
            Nenhum período aquisitivo ainda. Eles são gerados todo dia a partir da data de admissão.
        @else
            Os períodos de férias começam na admissão.
        @endif
    </p>
@else
    @php $saldoTotal = $periodos->sum(fn ($p) => $p->saldo()); @endphp
    <p class="mb-1">Saldo total: <strong>{{ $saldoTotal }} dia(s)</strong></p>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Período aquisitivo</th><th class="text-end">Direito</th><th class="text-end">Utilizados</th><th class="text-end">Saldo</th><th>Limite de gozo</th></tr></thead>
            <tbody>
            @foreach ($periodos as $periodo)
                @php $vencido = $periodo->saldo() > 0 && $periodo->limite_gozo->lt($hoje); @endphp
                <tr>
                    <td class="text-nowrap">{{ $periodo->aquisitivo_inicio->format('d/m/Y') }} a {{ $periodo->aquisitivo_fim->format('d/m/Y') }}</td>
                    <td class="text-end">{{ $periodo->dias_direito }}</td>
                    <td class="text-end">{{ $periodo->dias_utilizados }}</td>
                    <td class="text-end">{{ $periodo->saldo() }}</td>
                    <td class="text-nowrap">{{ $periodo->limite_gozo->format('d/m/Y') }}
                        @if ($vencido)<span class="badge text-bg-danger">vencido</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Solicitações de férias do colaborador e o formulário de nova solicitação (E4, fatia 3). --}}
@php
    $solicitacoes = $colaborador->feriasSolicitacoes;
    $cores = ['pendente' => 'warning', 'aprovada' => 'success', 'recusada' => 'danger', 'cancelada' => 'secondary'];
    $periodosComSaldo = $periodos->filter(fn ($p) => $p->saldo() > 0);
@endphp
@if ($solicitacoes->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Solicitação</th><th class="text-end">Dias</th><th>Situação</th><th></th></tr></thead>
            <tbody>
            @foreach ($solicitacoes as $s)
                <tr>
                    <td class="text-nowrap">{{ $s->inicio->format('d/m/Y') }} a {{ $s->fim->format('d/m/Y') }}</td>
                    <td class="text-end">{{ $s->dias }}</td>
                    <td><span class="badge text-bg-{{ $cores[$s->status->value] }}">{{ $s->status->rotulo() }}</span></td>
                    <td class="text-end">
                        @permission('ferias-solicitar')
                            @if (in_array($s->status, [\App\Enums\StatusSolicitacaoFerias::Pendente, \App\Enums\StatusSolicitacaoFerias::Aprovada], true))
                                <button type="button" class="btn btn-link btn-sm text-danger p-0" data-ferias-cancelar="{{ route('ferias.cancelar', $s->id) }}">Cancelar</button>
                            @endif
                        @endpermission
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@permission('ferias-solicitar')
    @if ($colaborador->status === \App\Enums\StatusColaborador::Ativo && $periodosComSaldo->isNotEmpty())
        <form id="form-ferias" class="row g-2 align-items-end mb-3" action="{{ route('ferias.solicitar', $colaborador->id) }}">
            <div class="col-sm-4">
                <label for="fer-periodo" class="small text-semibold">Período aquisitivo</label>
                <select id="fer-periodo" name="periodo_id" class="form-select form-select-sm" required>
                    @foreach ($periodosComSaldo->sortBy('aquisitivo_inicio') as $p)
                        <option value="{{ $p->id }}">{{ $p->aquisitivo_inicio->format('d/m/Y') }} a {{ $p->aquisitivo_fim->format('d/m/Y') }} (saldo {{ $p->saldo() }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-2"><label for="fer-inicio" class="small text-semibold">Início</label><input id="fer-inicio" type="date" name="inicio" class="form-control form-control-sm" required></div>
            <div class="col-sm-2"><label for="fer-fim" class="small text-semibold">Fim (inclusive)</label><input id="fer-fim" type="date" name="fim" class="form-control form-control-sm" required></div>
            <div class="col-sm-2"><label for="fer-obs" class="small text-semibold">Observação</label><input id="fer-obs" type="text" name="observacao" class="form-control form-control-sm" maxlength="255"></div>
            <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Solicitar</button></div>
        </form>
    @endif
@endpermission

@push('scripts')
<script>
    (() => {
        const recarregar = () => requestIntoModal(@json($recarregarUrl));
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const form = document.getElementById('form-ferias');
        if (form) form.addEventListener('submit', (ev) => {
            ev.preventDefault();
            axios.post(form.action, new FormData(form)).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
        });
        document.querySelectorAll('[data-ferias-cancelar]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm('Cancelar estas férias? Se já estavam aprovadas, os dias voltam ao saldo.')) return;
            axios.post(b.dataset.feriasCancelar, {}).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
        }));
    })();
</script>
@endpush

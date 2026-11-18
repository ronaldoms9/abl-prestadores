@extends('layouts.app', ['title' => 'Minhas férias'])

@php $cores = ['pendente' => 'warning', 'aprovada' => 'success', 'recusada' => 'danger', 'cancelada' => 'secondary']; $comSaldo = $periodos->filter(fn ($p) => $p->saldo() > 0); @endphp

@section('content')
<div class="container-fluid">
    <div class="card shadow mb-3">
        <div class="card-header border-0"><h5 class="mb-0">Minhas férias</h5></div>
        <div class="card-body">
            @if ($periodos->isEmpty())
                <p class="text-muted">Os períodos de férias começam na sua admissão.</p>
            @else
                <p class="mb-1">Saldo total: <strong>{{ $periodos->sum(fn ($p) => $p->saldo()) }} dia(s)</strong></p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Período aquisitivo</th><th class="text-end">Direito</th><th class="text-end">Utilizados</th><th class="text-end">Saldo</th><th>Limite de gozo</th></tr></thead>
                        <tbody>
                        @foreach ($periodos as $p)
                            <tr><td class="text-nowrap">{{ $p->aquisitivo_inicio->format('d/m/Y') }} a {{ $p->aquisitivo_fim->format('d/m/Y') }}</td><td class="text-end">{{ $p->dias_direito }}</td><td class="text-end">{{ $p->dias_utilizados }}</td><td class="text-end">{{ $p->saldo() }}</td><td>{{ $p->limite_gozo->format('d/m/Y') }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($comSaldo->isNotEmpty() && $colaborador->status === \App\Enums\StatusColaborador::Ativo)
        <div class="card shadow mb-3">
            <div class="card-header border-0"><h6 class="mb-0">Solicitar férias</h6></div>
            <div class="card-body">
                <form id="form-ferias" class="row g-2 align-items-end" action="{{ route('portal.ferias.solicitar') }}">
                    <div class="col-md-4">
                        <label for="periodo_id" class="small text-semibold">Período aquisitivo</label>
                        <select id="periodo_id" name="periodo_id" class="form-select form-select-sm" required>
                            @foreach ($comSaldo as $p)<option value="{{ $p->id }}">{{ $p->aquisitivo_inicio->format('d/m/Y') }} a {{ $p->aquisitivo_fim->format('d/m/Y') }} (saldo {{ $p->saldo() }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label for="inicio" class="small text-semibold">Início</label><input id="inicio" type="date" name="inicio" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label for="fim" class="small text-semibold">Fim (inclusive)</label><input id="fim" type="date" name="fim" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label for="observacao" class="small text-semibold">Observação</label><input id="observacao" type="text" name="observacao" class="form-control form-control-sm" maxlength="255"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Solicitar</button></div>
                </form>
            </div>
        </div>
    @endif

    <div class="card shadow">
        <div class="card-header border-0"><h6 class="mb-0">Minhas solicitações</h6></div>
        <div class="card-body">
            @if ($solicitacoes->isEmpty())
                <p class="text-muted mb-0">Nenhuma solicitação.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Período</th><th class="text-end">Dias</th><th>Situação</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($solicitacoes as $s)
                            <tr>
                                <td class="text-nowrap">{{ $s->inicio->format('d/m/Y') }} a {{ $s->fim->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $s->dias }}</td>
                                <td><span class="badge text-bg-{{ $cores[$s->status->value] }}">{{ $s->status->rotulo() }}</span></td>
                                <td class="text-end">@if ($s->status === \App\Enums\StatusSolicitacaoFerias::Pendente)<button type="button" class="btn btn-link btn-sm text-danger p-0" data-cancelar="{{ route('portal.ferias.cancelar', $s->id) }}">Cancelar</button>@endif</td>
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
        const form = document.getElementById('form-ferias');
        form?.addEventListener('submit', (ev) => { ev.preventDefault(); axios.post(form.action, new FormData(form)).then(ok).catch(falha); });
        document.querySelectorAll('[data-cancelar]').forEach((b) => b.addEventListener('click', () => {
            if (confirm('Cancelar esta solicitação?')) axios.post(b.dataset.cancelar, {}).then(ok).catch(falha);
        }));
    })();
</script>
@endpush

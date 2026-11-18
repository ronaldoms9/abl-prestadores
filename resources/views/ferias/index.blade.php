@extends('layouts.app', ['title' => 'Solicitações de férias'])

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0">
            <h5 class="mb-0">Solicitações de férias</h5>
            <ul class="nav nav-pills mt-2">
                <li class="nav-item"><a class="nav-link py-1 {{ $visao === 'decidir' ? 'active' : '' }}" href="{{ route('ferias.index') }}">Aguardando a minha decisão</a></li>
                @permission('ferias-list')
                    <li class="nav-item"><a class="nav-link py-1 {{ $visao === 'todas' ? 'active' : '' }}" href="{{ route('ferias.index', ['visao' => 'todas']) }}">Todas</a></li>
                @endpermission
            </ul>
            @if ($visao === 'todas')
                <form method="GET" class="d-flex align-items-center gap-2 mt-2">
                    <input type="hidden" name="visao" value="todas">
                    <label for="status" class="small text-semibold mb-0">Situação</label>
                    <select id="status" name="status" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                        <option value="">Todas</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected($status === $s)>{{ $s->rotulo() }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>
        <div class="card-body">
            @if ($solicitacoes->isEmpty())
                <p class="text-muted mb-0">{{ $visao === 'decidir' ? 'Nenhuma solicitação espera a sua decisão.' : 'Nenhuma solicitação.' }}</p>
            @else
                @if ($solicitacoes->count() >= $limite)
                    <div class="alert alert-info py-2 small">Mostrando as {{ $limite }} primeiras.</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Colaborador</th><th>Período</th><th class="text-end">Dias</th><th>Situação</th><th>Histórico</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($solicitacoes as $s)
                            @php $cores = ['pendente' => 'warning', 'aprovada' => 'success', 'recusada' => 'danger', 'cancelada' => 'secondary']; @endphp
                            <tr>
                                <td>{{ $s->colaborador->pessoaFisica->nome }}
                                    <div class="text-muted small">Pedido por {{ $s->solicitante?->name ?? '—' }} em {{ $s->created_at?->format('d/m/Y') }}</div></td>
                                <td class="text-nowrap">{{ $s->inicio->format('d/m/Y') }} a {{ $s->fim->format('d/m/Y') }}</td>
                                <td class="text-end">{{ $s->dias }}</td>
                                <td><span class="badge text-bg-{{ $cores[$s->status->value] }}">{{ $s->status->rotulo() }}</span>
                                    @if ($s->status === \App\Enums\StatusSolicitacaoFerias::Pendente)<span class="text-muted small">nível {{ $s->nivel_atual }}</span>@endif</td>
                                <td class="small text-muted">
                                    @foreach ($s->aprovacoes->sortBy('id') as $a)
                                        <div>N{{ $a->nivel }}: {{ $a->decisao->rotulo() }}@if ($a->aprovador) por {{ $a->aprovador->name }}@endif @if ($a->escalada)<em>(escalada)</em>@endif @if ($a->observacao)— {{ $a->observacao }}@endif</div>
                                    @endforeach
                                </td>
                                <td class="text-end text-nowrap">
                                    @if (in_array($s->id, $podeDecidir, true))
                                        <a href="javascript:requestIntoModal('{{ route('ferias.decidir.form', $s->id) }}')" class="btn btn-sm btn-primary">Decidir</a>
                                    @endif
                                    @if ($podeCancelar && in_array($s->status, [\App\Enums\StatusSolicitacaoFerias::Pendente, \App\Enums\StatusSolicitacaoFerias::Aprovada], true))
                                        <button type="button" class="btn btn-link btn-sm text-danger" data-cancelar="{{ route('ferias.cancelar', $s->id) }}">Cancelar</button>
                                    @endif
                                </td>
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
    document.querySelectorAll('[data-cancelar]').forEach((b) => b.addEventListener('click', () => {
        if (!confirm('Cancelar estas férias? Se já estavam aprovadas, os dias voltam ao saldo.')) return;
        axios.post(b.dataset.cancelar, {})
            .then((r) => { showNotify('Sucesso', r.data.message); setTimeout(() => location.reload(), 800); })
            .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
    }));
</script>
@endpush

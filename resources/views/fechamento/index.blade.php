@extends('layouts.app', ['title' => 'Fechamento mensal'])

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0 d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0 me-auto">Fechamento mensal</h5>
            @permission('fechamento-conferir')
                <form id="form-abrir" class="d-flex align-items-center gap-2" action="{{ route('fechamento.abrir') }}">
                    <label for="ano_mes" class="small text-semibold mb-0">Mês</label>
                    <input id="ano_mes" type="month" name="ano_mes" value="{{ $mesSugerido }}" max="{{ $mesSugerido }}" class="form-control form-control-sm" required>
                    <button type="submit" class="btn btn-primary btn-sm">Abrir competência</button>
                </form>
            @endpermission
        </div>
        <div class="card-body">
            <p class="text-muted small">Abrir uma competência calcula remuneração e benefícios de todos os colaboradores que estiveram ativos no mês. O RH confere e ajusta; o financeiro aprova, e a partir daí os valores do mês não mudam mais.</p>
            @if ($competencias->isEmpty())
                <p class="text-muted mb-0">Nenhuma competência aberta ainda.</p>
            @else
                @php $cores = ['aberta' => 'secondary', 'em_conferencia' => 'warning', 'aprovada' => 'success', 'fechada' => 'dark']; @endphp
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Mês</th><th>Situação</th><th class="text-end">Colaboradores</th><th class="text-end">Total líquido</th><th>Aprovada em</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($competencias as $c)
                            <tr>
                                <td>{{ substr($c->ano_mes, 5, 2) }}/{{ substr($c->ano_mes, 0, 4) }}</td>
                                <td><span class="badge text-bg-{{ $cores[$c->status->value] }}">{{ $c->status->rotulo() }}</span></td>
                                <td class="text-end">{{ $c->fechamentos_count }}</td>
                                <td class="text-end">R$ {{ number_format((float) $c->fechamentos_sum_total_liquido, 2, ',', '.') }}</td>
                                <td>{{ $c->aprovada_em?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="text-end"><a href="{{ route('fechamento.show', $c->id) }}" class="btn btn-outline-primary btn-sm">Abrir</a></td>
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
    document.getElementById('form-abrir')?.addEventListener('submit', function (ev) {
        ev.preventDefault();
        axios.post(this.action, new FormData(this))
            .then((r) => { showNotify('Sucesso', r.data.message); setTimeout(() => { location.href = r.data.data.url; }, 1200); })
            .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
    });
</script>
@endpush

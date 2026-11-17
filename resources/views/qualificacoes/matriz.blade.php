@extends('layouts.app', ['title' => 'Matriz de qualificações'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0">
            <h5 class="mb-1">Matriz de qualificações por cargo</h5>
            <p class="text-muted small mb-0">Em cada cruzamento, escolha se o cargo não exige, exige como opcional ou exige como obrigatória. Só as obrigatórias geram pendência e alerta.</p>
        </div>
        <div class="card-body">
            @if ($cargos->isEmpty() || $tipos->isEmpty())
                <p class="text-muted mb-0">Cadastre ao menos um cargo ativo e um tipo de qualificação ativo para montar a matriz.</p>
            @else
                <form id="form-matriz" onsubmit="return false">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Cargo</th>
                                    @foreach ($tipos as $tipo)<th class="text-center small">{{ $tipo->nome }}</th>@endforeach
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($cargos as $cargo)
                                <tr>
                                    <th class="text-nowrap">{{ $cargo->nome }}</th>
                                    @foreach ($tipos as $tipo)
                                        @php $estado = $estados[$cargo->id.':'.$tipo->id] ?? 'nenhuma'; @endphp
                                        <td>
                                            <select class="form-select form-select-sm" name="celulas[{{ $cargo->id }}][{{ $tipo->id }}]" aria-label="{{ $cargo->nome }} — {{ $tipo->nome }}">
                                                <option value="nenhuma" @selected($estado === 'nenhuma')>—</option>
                                                <option value="opcional" @selected($estado === 'opcional')>Opcional</option>
                                                <option value="obrigatoria" @selected($estado === 'obrigatoria')>Obrigatória</option>
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" id="salvar-matriz" class="btn btn-primary">Salvar matriz</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    document.getElementById('salvar-matriz')?.addEventListener('click', () => {
        const form = document.getElementById('form-matriz');
        axios.post(@json(route('qualificacao.matriz.salvar')), new FormData(form))
            .then((r) => showNotify('Sucesso', r.data.message))
            .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
    });
</script>
@endpush

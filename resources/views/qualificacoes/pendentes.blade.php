@extends('layouts.app', ['title' => 'Pendências de qualificação'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0">
            <h5 class="mb-1">Pendências de qualificação</h5>
            <p class="text-muted small mb-2">Colaboradores sem uma qualificação obrigatória do cargo, com ela vencida ou com ela vencendo em até {{ $dias }} dias.</p>
            <form method="get" class="row g-2 align-items-end">
                <div class="col-sm-4 col-lg-3">
                    <label for="cargo_id" class="small text-semibold">Cargo</label>
                    <select id="cargo_id" name="cargo_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach ($cargos as $cargo)
                            <option value="{{ $cargo->id }}" @selected($cargoId === $cargo->id)>{{ $cargo->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
        <div class="card-body">
            @if ($situacoes->isEmpty())
                <p class="text-muted mb-0">Nenhuma pendência. Todos os colaboradores com cargo estão em dia.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Colaborador</th><th>Cargo</th><th>Ausentes</th><th>Vencidas</th><th>A vencer</th></tr></thead>
                        <tbody>
                        @foreach ($situacoes as $s)
                            <tr>
                                <td><a href="javascript:requestIntoModal('{{ route('colaborador.show', $s['colaborador']->id) }}')">{{ $s['colaborador']->pessoaFisica->nome }}</a></td>
                                <td>{{ $s['colaborador']->cargo->nome }}</td>
                                <td>{{ $s['ausentes']->pluck('nome')->implode(', ') ?: '—' }}</td>
                                <td class="text-danger">{{ $s['vencidas']->map(fn ($q) => $q->tipo->nome.' ('.$q->data_validade->format('d/m/Y').')')->implode(', ') ?: '—' }}</td>
                                <td class="text-warning">{{ $s['a_vencer']->map(fn ($q) => $q->tipo->nome.' ('.$q->data_validade->format('d/m/Y').')')->implode(', ') ?: '—' }}</td>
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

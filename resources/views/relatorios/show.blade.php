@extends('layouts.app', ['title' => $relatorio->titulo()])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h5 class="mb-0 me-auto">{{ $relatorio->titulo() }}</h5>
                <a href="{{ route('relatorio.index') }}" class="btn btn-link btn-sm">Todos os relatórios</a>
                <a href="{{ route('relatorio.exportar', [$relatorio->slug(), 'xlsx'] + request()->query()) }}" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a href="{{ route('relatorio.exportar', [$relatorio->slug(), 'pdf'] + request()->query()) }}" class="btn btn-outline-danger btn-sm" id="btn-pdf"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            </div>
            <p class="text-muted small mb-2">{{ $relatorio->descricao() }}</p>
            <form method="GET" class="row g-2 align-items-end">
                @foreach ($relatorio->filtros() as $nome => [$rotulo, $tipo])
                    <div class="col-sm-6 col-lg-3">
                        <label for="f-{{ $nome }}" class="small text-semibold">{{ $rotulo }}</label>
                        @if ($tipo === 'mes')
                            <input id="f-{{ $nome }}" type="month" name="{{ $nome }}" value="{{ $filtros[$nome] }}" class="form-control form-control-sm">
                        @elseif ($tipo === 'numero')
                            <input id="f-{{ $nome }}" type="number" min="0" name="{{ $nome }}" value="{{ $filtros[$nome] }}" class="form-control form-control-sm">
                        @else
                            <select id="f-{{ $nome }}" name="{{ $nome }}" class="form-select form-select-sm">
                                @if ($tipo !== 'status')<option value="">Todos</option>@endif
                                @foreach ($opcoes[$tipo] ?? [] as $valor => $texto)<option value="{{ $valor }}" @selected((string) $filtros[$nome] === (string) $valor)>{{ $texto }}</option>@endforeach
                            </select>
                        @endif
                    </div>
                @endforeach
                <div class="col-auto"><button class="btn btn-primary btn-sm">Filtrar</button></div>
            </form>
        </div>
        <div class="card-body">
            @if ($resultado->linhas === [])
                <p class="text-muted mb-0">Nenhum resultado para esses filtros.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr>@foreach ($resultado->colunas as $c)<th class="{{ in_array($c['tipo'], ['dinheiro', 'numero'], true) ? 'text-end' : '' }}">{{ $c['rotulo'] }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($resultado->linhas as $l)
                            <tr>@foreach ($resultado->colunas as $c)<td class="{{ in_array($c['tipo'], ['dinheiro', 'numero'], true) ? 'text-end' : '' }}">{{ $l[$c['chave']] ?? '' }}</td>@endforeach</tr>
                        @endforeach
                        </tbody>
                        @if ($resultado->total)
                            <tfoot><tr class="fw-semibold">@foreach ($resultado->colunas as $c)<td class="{{ in_array($c['tipo'], ['dinheiro', 'numero'], true) ? 'text-end' : '' }}">{{ $resultado->total[$c['chave']] ?? '' }}</td>@endforeach</tr></tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    document.getElementById('btn-pdf')?.addEventListener('click', function (ev) {
        ev.preventDefault();
        axios.get(this.href, { responseType: 'blob', headers: { Accept: 'application/pdf' } }).then((r) => {
            const url = URL.createObjectURL(r.data); const a = document.createElement('a');
            a.href = url; a.download = 'relatorio.pdf'; a.click(); URL.revokeObjectURL(url);
        }).catch(async (e) => {
            let msg = 'Não foi possível gerar o PDF.';
            try { msg = JSON.parse(await e.response.data.text()).message || msg; } catch (_) {}
            showNotify('Atenção', msg, 'error');
        });
    });
</script>
@endpush

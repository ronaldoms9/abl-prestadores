@extends('layouts.app', ['title' => 'Feriados'])

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0 d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0 me-auto">Feriados de {{ $ano }}</h5>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="ano" class="small text-semibold mb-0">Ano</label>
                <input id="ano" type="number" name="ano" value="{{ $ano }}" min="2000" max="2100" class="form-control form-control-sm" style="width: 6rem">
                <button class="btn btn-outline-secondary btn-sm">Ver</button>
            </form>
            @permission('feriado-gerir')
                <button type="button" id="btn-importar" class="btn btn-outline-primary btn-sm" data-url="{{ route('feriado.importar') }}" data-ano="{{ $ano }}">Importar nacionais de {{ $ano }}</button>
                <a href="javascript:requestIntoModal('{{ route('feriado.form') }}')" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo feriado</a>
            @endpermission
        </div>
        <div class="card-body">
            <p class="text-muted small">Feriado nacional vale para todas as obras. Estadual e municipal valem só para a obra escolhida. O dia de feriado não conta como dia trabalhado no benefício por dia.</p>
            @if ($feriados->isEmpty())
                <p class="text-muted mb-0">Nenhum feriado cadastrado em {{ $ano }}.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Data</th><th>Descrição</th><th>Abrangência</th><th>Obra</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($feriados as $f)
                            <tr>
                                <td class="text-nowrap">{{ $f->data->format('d/m/Y') }}</td>
                                <td>{{ $f->descricao }}</td>
                                <td>{{ $f->abrangencia->rotulo() }}</td>
                                <td>{{ $f->obra ? (($f->obra->codigo ? $f->obra->codigo.' · ' : '').$f->obra->descricao) : 'Todas' }}</td>
                                <td class="text-end text-nowrap">
                                    @permission('feriado-gerir')
                                        <a href="javascript:requestIntoModal('{{ route('feriado.form', $f->id) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                                        <a href="javascript:deleteDefault('{{ route('feriado.delete', $f->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
                                    @endpermission
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
    document.getElementById('btn-importar')?.addEventListener('click', function () {
        if (!confirm('Importar os feriados nacionais de ' + this.dataset.ano + ' da BrasilAPI? O que já existe não muda.')) { return; }
        axios.post(this.dataset.url, { ano: this.dataset.ano })
            .then((r) => { showNotify('Sucesso', r.data.message, 'success'); setTimeout(() => location.reload(), 1500); })
            .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Não foi possível importar.', 'error'));
    });
</script>
@endpush

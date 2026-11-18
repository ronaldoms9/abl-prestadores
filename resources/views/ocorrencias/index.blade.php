@extends('layouts.app', ['title' => 'Ocorrências'])

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0 d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0 me-auto">Faltas e afastamentos · {{ $mes->format('m/Y') }}</h5>
            <form method="GET" class="d-flex align-items-center gap-2">
                <label for="mes" class="small text-semibold mb-0">Mês</label>
                <input id="mes" type="month" name="mes" value="{{ $mes->format('Y-m') }}" class="form-control form-control-sm">
                <button class="btn btn-outline-secondary btn-sm">Ver</button>
            </form>
            @permission('ocorrencia-gerir')
                <a href="javascript:requestIntoModal('{{ route('ocorrencia.form') }}')" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nova ocorrência</a>
            @endpermission
        </div>
        <div class="card-body">
            <p class="text-muted small">Falta ou afastamento não abonado tira o dia do benefício por dia trabalhado; abonado não tira, mas fica registrado. Mês já fechado não aceita lançamento.</p>
            @if ($ocorrencias->isEmpty())
                <p class="text-muted mb-0">Nenhuma ocorrência neste mês.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Colaborador</th><th>Tipo</th><th>Período</th><th>Abonada</th><th>Observação</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($ocorrencias as $o)
                            <tr>
                                <td>{{ $o->colaborador->pessoaFisica->nome }}</td>
                                <td>{{ $o->tipo->rotulo() }}</td>
                                <td class="text-nowrap">{{ $o->inicio->format('d/m/Y') }}@if (! $o->inicio->equalTo($o->fim)) a {{ $o->fim->format('d/m/Y') }}@endif</td>
                                <td>{{ $o->abona ? 'Sim' : 'Não' }}</td>
                                <td class="text-muted small">{{ $o->observacao }}</td>
                                <td class="text-end text-nowrap">
                                    @permission('ocorrencia-gerir')
                                        <a href="javascript:requestIntoModal('{{ route('ocorrencia.form', $o->id) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                                        <a href="javascript:deleteDefault('{{ route('ocorrencia.delete', $o->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
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

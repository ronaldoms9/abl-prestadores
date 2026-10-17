@extends('layouts.modal', [
    'title' => $obra->descricao,
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-0">
        <dt class="col-sm-3">Empresa</dt>
        <dd class="col-sm-9">
            {{ $obra->empresa ? $obra->empresa->uau_codigo.' · '.$obra->empresa->razao_social : '—' }}
            @if ($obra->empresa)
                <span class="text-muted small">({{ $obra->empresa->cnpj_formatado }})</span>
            @endif
        </dd>

        <dt class="col-sm-3">Código</dt>
        <dd class="col-sm-9">{{ $obra->uau_codigo }}</dd>

        <dt class="col-sm-3">Situação</dt>
        <dd class="col-sm-9">@include('cadastros._situacao', ['ativo' => $obra->ativo])</dd>

        <dt class="col-sm-3">Status no UAU</dt>
        <dd class="col-sm-9">{{ $obra->statusRotulo() }}</dd>

        <dt class="col-sm-3">Tipo no UAU</dt>
        <dd class="col-sm-9">{{ $obra->tipo_uau ?? '—' }}</dd>

        <dt class="col-sm-3">Período</dt>
        <dd class="col-sm-9">{{ $obra->data_inicio?->format('d/m/Y') ?? '—' }} a {{ $obra->data_fim?->format('d/m/Y') ?? '—' }}</dd>

        <dt class="col-sm-3">Endereço</dt>
        <dd class="col-sm-9">{{ $obra->endereco?->formatado() ?: '—' }}</dd>

        <dt class="col-sm-3">Sincronizado em</dt>
        <dd class="col-sm-9">{{ $obra->sincronizado_em?->format('d/m/Y H:i') ?? '—' }}</dd>
    </dl>
@endsection

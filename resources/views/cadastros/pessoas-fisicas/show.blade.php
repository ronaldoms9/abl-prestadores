@extends('layouts.modal', [
    'title' => $pessoa->nome,
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-3">
        <dt class="col-sm-3">CPF</dt>
        <dd class="col-sm-9">{{ $pessoa->cpf_formatado }}</dd>

        <dt class="col-sm-3">Situação</dt>
        <dd class="col-sm-9">@include('cadastros._situacao', ['ativo' => $pessoa->ativo])</dd>

        <dt class="col-sm-3">Nascimento</dt>
        <dd class="col-sm-9">{{ $pessoa->nascimento?->format('d/m/Y') ?? '—' }}</dd>

        <dt class="col-sm-3">E-mail</dt>
        <dd class="col-sm-9">{{ $pessoa->email ?? '—' }}</dd>

        <dt class="col-sm-3">Telefone</dt>
        <dd class="col-sm-9">{{ $pessoa->telefone ?? '—' }}</dd>

        <dt class="col-sm-3">Endereço</dt>
        <dd class="col-sm-9">{{ $pessoa->endereco?->formatado() ?: '—' }}</dd>

        @include('cadastros._rodape-uau', ['registro' => $pessoa])
    </dl>

    <h6 class="text-semibold">Pessoas jurídicas sugeridas</h6>
    @include('cadastros._vinculos', [
        'pessoas' => $pessoa->pessoasJuridicasSugeridas,
        'documento' => fn ($pj) => $pj->cnpj_formatado,
        'nome' => fn ($pj) => $pj->razao_social,
    ])
@endsection

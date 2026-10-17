@extends('layouts.modal', [
    'title' => $pessoa->razao_social,
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-3">
        <dt class="col-sm-3">CNPJ</dt>
        <dd class="col-sm-9">{{ $pessoa->cnpj_formatado }}</dd>

        <dt class="col-sm-3">Situação</dt>
        <dd class="col-sm-9">@include('cadastros._situacao', ['ativo' => $pessoa->ativo])</dd>

        <dt class="col-sm-3">Nome fantasia</dt>
        <dd class="col-sm-9">{{ $pessoa->nome_fantasia ?? '—' }}</dd>

        <dt class="col-sm-3">Inscrições</dt>
        <dd class="col-sm-9">
            Estadual: {{ $pessoa->inscricao_estadual ?? '—' }} · Municipal: {{ $pessoa->inscricao_municipal ?? '—' }}
        </dd>

        <dt class="col-sm-3">Regime tributário</dt>
        <dd class="col-sm-9">{{ $pessoa->regime_tributario ?? '—' }}</dd>

        <dt class="col-sm-3">E-mail</dt>
        <dd class="col-sm-9">{{ $pessoa->email ?? '—' }}</dd>

        <dt class="col-sm-3">Telefone</dt>
        <dd class="col-sm-9">{{ $pessoa->telefone ?? '—' }}</dd>

        <dt class="col-sm-3">Endereço</dt>
        <dd class="col-sm-9">{{ $pessoa->endereco?->formatado() ?: '—' }}</dd>

        @include('cadastros._rodape-uau', ['registro' => $pessoa])
    </dl>

    <h6 class="text-semibold">Pessoas físicas sugeridas</h6>
    @include('cadastros._vinculos', [
        'pessoas' => $pessoa->pessoasFisicasSugeridas,
        'documento' => fn ($pf) => $pf->cpf_formatado,
        'nome' => fn ($pf) => $pf->nome,
    ])
@endsection

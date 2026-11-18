@extends('layouts.app', ['title' => 'Meu portal'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0"><h5 class="mb-0">Olá, {{ $colaborador->pessoaFisica->nome }}</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><a href="{{ route('portal.ferias') }}" class="text-decoration-none"><div class="border rounded p-3 h-100"><div class="text-muted small">Saldo de férias</div><div class="h4 mb-0">{{ $saldo }} dia(s)</div>@if ($feriasPendentes > 0)<div class="small text-muted">{{ $feriasPendentes }} solicitação(ões) esperando decisão</div>@endif</div></a></div>
                <div class="col-md-4"><a href="{{ route('portal.notas') }}" class="text-decoration-none"><div class="border rounded p-3 h-100"><div class="text-muted small">Notas fiscais a enviar</div><div class="h4 mb-0">{{ $notasAEnviar }}</div></div></a></div>
            </div>
        </div>
    </div>
</div>
@endsection

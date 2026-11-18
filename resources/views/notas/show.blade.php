@extends('layouts.modal', ['title' => 'Nota de '.$nota->colaborador->pessoaFisica->nome.' · '.substr($nota->competencia->ano_mes, 5, 2).'/'.substr($nota->competencia->ano_mes, 0, 4)])

@php $fmt = fn ($v) => $v === null ? '—' : 'R$ '.number_format((float) $v, 2, ',', '.'); $cnpj = fn ($v) => $v ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $v) : '—'; @endphp

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <p><span class="badge text-bg-secondary">{{ $nota->status->rotulo() }}</span> <span class="text-muted small">{{ $nota->origem_dados->rotulo() }}@if ($nota->enviadaPor) · enviada por {{ $nota->enviadaPor->name }}@endif</span></p>
    @foreach ($nota->divergencias ?? [] as $d)<div class="alert alert-warning py-2 small">{{ $d['mensagem'] }}</div>@endforeach
    @if ($nota->motivo_recusa)<div class="alert alert-danger py-2 small">Recusada: {{ $nota->motivo_recusa }}</div>@endif
    @if ($nota->justificativa_aprovacao)<div class="alert alert-info py-2 small">Aprovada com justificativa: {{ $nota->justificativa_aprovacao }}</div>@endif
    <dl class="row mb-2 small">
        <dt class="col-sm-4">Número / série</dt><dd class="col-sm-8">{{ $nota->numero ?? '—' }} / {{ $nota->serie ?? '—' }}</dd>
        <dt class="col-sm-4">Emissão</dt><dd class="col-sm-8">{{ $nota->data_emissao?->format('d/m/Y') ?? '—' }}</dd>
        <dt class="col-sm-4">Emissor</dt><dd class="col-sm-8">{{ $cnpj($nota->cnpj_emissor) }}</dd>
        <dt class="col-sm-4">Tomador</dt><dd class="col-sm-8">{{ $cnpj($nota->cnpj_tomador) }}</dd>
        <dt class="col-sm-4">Valor bruto</dt><dd class="col-sm-8">{{ $fmt($nota->valor_bruto) }}</dd>
        <dt class="col-sm-4">Retenções</dt><dd class="col-sm-8">{{ $fmt($nota->valor_retencoes) }}@foreach ($nota->retencoes as $r) · {{ $r->tributo->value }} {{ $fmt($r->valor) }}@endforeach</dd>
        <dt class="col-sm-4">Valor líquido</dt><dd class="col-sm-8">{{ $fmt($nota->valor_liquido) }}</dd>
        <dt class="col-sm-4">Chave de acesso</dt><dd class="col-sm-8 text-break">{{ $nota->chave_acesso ?? '—' }}</dd>
    </dl>
    @if ($nota->pdf_path)<a class="btn btn-outline-secondary btn-sm" href="{{ route('nota.arquivo', [$nota->id, 'pdf']) }}">Baixar PDF</a>@endif
    @if ($nota->xml_path)<a class="btn btn-outline-secondary btn-sm" href="{{ route('nota.arquivo', [$nota->id, 'xml']) }}">Baixar XML</a>@endif
@endsection

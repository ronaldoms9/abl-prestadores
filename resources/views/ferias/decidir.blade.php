@extends('layouts.modal', [
    'title' => 'Férias de '.$solicitacao->colaborador->pessoaFisica->nome,
    'formroute' => route('ferias.decidir', $solicitacao->id),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Registrar decisão <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <p class="mb-2"><strong>{{ $solicitacao->inicio->format('d/m/Y') }} a {{ $solicitacao->fim->format('d/m/Y') }}</strong> ({{ $solicitacao->dias }} dia(s))</p>
    @if ($solicitacao->observacao)<p class="text-muted small">Observação do pedido: {{ $solicitacao->observacao }}</p>@endif
    @foreach ($solicitacao->aprovacoes->sortBy('id') as $a)
        <div class="small text-muted">Nível {{ $a->nivel }}: {{ $a->decisao->rotulo() }}@if ($a->aprovador) por {{ $a->aprovador->name }}@endif @if ($a->observacao)— {{ $a->observacao }}@endif</div>
    @endforeach
    <div class="form-group mt-3">
        <label class="text-semibold small d-block">Decisão</label>
        <div class="form-check form-check-inline">
            <input id="dec-ap" class="form-check-input" type="radio" name="decisao" value="aprovada" checked>
            <label for="dec-ap" class="form-check-label">Aprovar</label>
        </div>
        <div class="form-check form-check-inline">
            <input id="dec-re" class="form-check-input" type="radio" name="decisao" value="recusada">
            <label for="dec-re" class="form-check-label">Recusar</label>
        </div>
    </div>
    <div class="form-group mt-2">
        <label for="observacao" class="text-semibold small">Observação (obrigatória na recusa)</label>
        <input id="observacao" type="text" name="observacao" class="form-control" maxlength="255">
    </div>
@endsection

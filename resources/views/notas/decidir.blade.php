@extends('layouts.modal', [
    'title' => 'Decidir a nota de '.$nota->colaborador->pessoaFisica->nome,
    'formroute' => route('nota.decidir', $nota->id),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Registrar decisão <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    @foreach ($nota->divergencias ?? [] as $d)<div class="alert alert-warning py-2 small">{{ $d['mensagem'] }}</div>@endforeach
    <p class="small mb-2">Nota {{ $nota->numero ?? '—' }} · valor bruto R$ {{ number_format((float) $nota->valor_bruto, 2, ',', '.') }}</p>
    <div class="form-group">
        <div class="form-check form-check-inline"><input id="d-ap" class="form-check-input" type="radio" name="decisao" value="aprovar" checked><label for="d-ap" class="form-check-label">Aprovar</label></div>
        <div class="form-check form-check-inline"><input id="d-re" class="form-check-input" type="radio" name="decisao" value="recusar"><label for="d-re" class="form-check-label">Recusar</label></div>
    </div>
    <div class="form-group mt-2">
        <label for="texto" class="text-semibold small">{{ $nota->divergencias ? 'Justificativa (obrigatória para aprovar com divergência) ou motivo da recusa' : 'Motivo da recusa (obrigatório ao recusar)' }}</label>
        <input id="texto" type="text" name="texto" class="form-control" maxlength="255">
    </div>
@endsection

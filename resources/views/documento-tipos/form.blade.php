@extends('layouts.modal', [
    'title' => $tipo->exists ? 'Editar tipo de documento' : 'Novo tipo de documento',
    'formroute' => route('documento-tipo.store', $tipo->exists ? [$tipo->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome do tipo</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $tipo->nome }}" required>
    </div>
    <div class="form-group mt-2">
        <label for="aplica_a" class="text-semibold small">Vale para</label>
        <select id="aplica_a" name="aplica_a" class="form-select">
            @foreach ($aplicacoes as $a)
                <option value="{{ $a->value }}" @selected($tipo->aplica_a === $a)>{{ $a->rotulo() }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-check form-switch mt-3">
        <input type="hidden" name="exige_validade" value="0">
        <input id="exige_validade" type="checkbox" role="switch" class="form-check-input" name="exige_validade" value="1" @checked($tipo->exige_validade)>
        <label for="exige_validade" class="form-check-label">Exige data de validade (alimenta os alertas)</label>
    </div>
    @if ($tipo->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($tipo->ativo)>
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
    @endif
@endsection

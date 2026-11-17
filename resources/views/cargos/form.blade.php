@extends('layouts.modal', [
    'title' => $cargo->exists ? 'Editar cargo' : 'Novo cargo',
    'formroute' => route('cargo.store', $cargo->exists ? [$cargo->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome do cargo</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $cargo->nome }}" required>
    </div>
    <div class="form-group mt-2">
        <label for="descricao" class="text-semibold small">Descrição</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="2">{{ $cargo->descricao }}</textarea>
    </div>
    <div class="row mt-2">
        <div class="col-sm-6 form-group">
            <label for="faixa_min" class="text-semibold small">Faixa mínima (R$)</label>
            <input id="faixa_min" type="number" step="0.01" min="0" name="faixa_min" class="form-control" value="{{ $cargo->faixa_min }}">
        </div>
        <div class="col-sm-6 form-group">
            <label for="faixa_max" class="text-semibold small">Faixa máxima (R$)</label>
            <input id="faixa_max" type="number" step="0.01" min="0" name="faixa_max" class="form-control" value="{{ $cargo->faixa_max }}">
        </div>
    </div>
    <p class="text-muted small mb-0">A remuneração fora da faixa gera um aviso, não bloqueia.</p>
    @if ($cargo->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($cargo->ativo)>
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
    @endif
@endsection

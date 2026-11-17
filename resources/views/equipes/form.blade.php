@extends('layouts.modal', [
    'title' => $equipe->exists ? 'Editar equipe' : 'Nova equipe',
    'formroute' => route('equipe.store', $equipe->exists ? [$equipe->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome da equipe</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $equipe->nome }}" required>
    </div>
    <div class="form-group mt-2">
        <label for="descricao" class="text-semibold small">Descrição</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="3">{{ $equipe->descricao }}</textarea>
    </div>
    @if ($equipe->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($equipe->ativo)>
            <label for="ativo" class="form-check-label">Ativa</label>
        </div>
    @endif
@endsection

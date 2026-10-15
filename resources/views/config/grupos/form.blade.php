@extends('layouts.modal', [
    'title' => 'Cadastro de grupo de acesso',
    'formroute'=> route('grupo.store',[$grupo->id])
])

@section('footer')
    <button type="button" data-dismiss="modal"
            class="btn btn-link"><i class="fa fa-times"></i> Fechar
    </button>

    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>

@endsection

@section('content')
    <div class="row">
        @if(!$grupo->id)
        <div class="col form-group">
            <label for="nome" class="text-semibold small">Idenficador</label>
            <input id="nome" type="text" value="" class="form-control" name="identificador">
            <span class="text-muted small">Digite um código sem espaços</span>
        </div>
        @endif

        <div class="col form-group">
            <label for="nome_grupo" class="text-semibold small">Nome do Grupo</label>
            <input id="nome_grupo" type="text" value="{{$grupo->display_name}}" class="form-control" name="nome_grupo">
        </div>
    </div>
    <div class="row">
        <div class="col form-group">
            <label for="descricao" class="text-semibold small">Descrição</label>
            <input id="descricao" type="text" value="{{$grupo->description}}" class="form-control" name="descricao">
        </div>
    </div>

    <h3 class="text-center">Permissões</h3>
    <hr>

    <div class="row">
        @foreach($permissoes as $permission)
        <div class="col-4 form-group">

            <input type="checkbox" {{ in_array($permission->id, $permissionsId) ? 'checked' : '' }} name="permissoes[]" value="{{ $permission->id }}">
            <label class="small"><span class="text-uppercase text-semibold">{{ ($permission->module)  }}</span> - {{ $permission->display_name }}</label>
        </div>
        @endforeach

    </div>
@endsection

@extends('layouts.modal', [
    'title' => 'Cadastro de usuário',
    'formroute'=> route('user.store',[$grupo->id])
])
{{--@section('size', 'modal-sm')--}}
{{--@section('title', "Inclusao de Etapa")--}}
@section('footer')
    <button type="button" data-bs-dismiss="modal"
            class="btn btn-link"><i class="fa fa-times"></i> Fechar
    </button>

    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>

@endsection
@section('content')


    <div class="row">
        <div class="col-7 form-group">
            <label for="nome" class="text-semibold small">Nome</label>
            <input id="nome" type="text" value="{{$user->name}}" class="form-control" name="nome">
        </div>

        <div class="col form-group">
            <label for="cpf" class="text-semibold small">CPF</label>
            <input id="cpf" type="text" value="{{$user->cpf}}" class="form-control" name="cpf">
        </div>

    </div>
    <div class="row">
        <div class="col-7 form-group">
            <label for="email" class="text-semibold small">E-mail</label>
            <input id="email" type="email" value="{{$user->email}}" class="form-control" name="email">
        </div>
        <div class="col form-group">
            <label for="telefone" class="text-semibold small">Telefone</label>
            <input id="telefone" type="text" value="{{$user->telefone}}" class="form-control" name="telefone">
        </div>
    </div>
    <div class="row">

        <div class="col-7 form-group">
            <label for="senha" class="text-semibold small">Senha</label>
            <input id="senha" type="password" value="" class="form-control" name="senha">
            @if($user->id)
            <span class="text-muted small">Deixe em branco se não deseja alterar</span>
            @endif
        </div>
        <div class="col form-group">
            <label for="grupo_acesso" class="text-semibold small">Grupo de acesso</label>
            <select id="grupo_acesso" name="grupo_acesso" class="form-control">
                <option value="">SELECIONE</option>
                @foreach($grupos as $grupo)
                    <option {{ $user->roles()->value('id') == $grupo->id ?  'selected' : '' }} value="{{ $grupo->id }}">{{ $grupo->display_name }}</option>
                @endforeach
            </select>
        </div>
    </div>
@endsection

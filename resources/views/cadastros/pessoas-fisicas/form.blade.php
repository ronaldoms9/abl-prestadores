@extends('layouts.modal', [
    'title' => $pessoa->exists ? 'Editar pessoa física' : 'Nova pessoa física',
    'formroute' => route('pessoa-fisica.store', $pessoa->exists ? [$pessoa->id] : []),
    'size' => 'modal-xl',
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    @if ($pessoa->uau_codigo)
        <div class="alert alert-warning small">
            Esta pessoa vem do UAU (código {{ $pessoa->uau_codigo }}). Enquanto a gravação no UAU não estiver ligada,
            a sincronização diária pode desfazer as alterações feitas aqui.
        </div>
    @endif

    <div class="row">
        <div class="col-sm-8 form-group">
            <label for="nome" class="text-semibold small">Nome completo</label>
            <input id="nome" type="text" name="nome" class="form-control" value="{{ $pessoa->nome }}" required>
        </div>
        <div class="col-sm-4 form-group">
            <label for="cpf" class="text-semibold small">CPF</label>
            <input id="cpf" type="text" name="cpf" class="form-control" value="{{ $pessoa->cpf_formatado }}" inputmode="numeric" required>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-3 form-group">
            <label for="nascimento" class="text-semibold small">Nascimento</label>
            <input id="nascimento" type="date" name="nascimento" class="form-control" value="{{ $pessoa->nascimento?->format('Y-m-d') }}">
        </div>
        <div class="col-sm-5 form-group">
            <label for="email" class="text-semibold small">E-mail</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ $pessoa->email }}">
        </div>
        <div class="col-sm-4 form-group">
            <label for="telefone" class="text-semibold small">Telefone</label>
            <input id="telefone" type="text" name="telefone" class="form-control" value="{{ $pessoa->telefone }}" inputmode="tel">
        </div>
    </div>

    @include('cadastros._form-endereco')
    @include('cadastros._form-bancos')

    @if ($pessoa->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($pessoa->ativo)>
            <label for="ativo" class="form-check-label">Ativa</label>
        </div>
    @endif
@endsection

@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        {{-- Alertas por e-mail que ninguém recebe (README D2): só o administrador de perfis vê. --}}
        @permission('grupo-edit')
            @foreach (\App\Support\AvisosAdmin::alertasSemDestinatario() as $permissao => $aviso)
                <div class="alert alert-warning">
                    <strong>Ninguém recebe o {{ $aviso['descricao'] }}.</strong>
                    Desde {{ \Carbon\Carbon::parse($aviso['desde'])->format('d/m/Y') }}, nenhum usuário ativo tem a permissão
                    <code>{{ $permissao }}</code>. Dê a permissão a um perfil em Configurações › Perfis.
                </div>
            @endforeach
        @endpermission
    </div>
@endsection

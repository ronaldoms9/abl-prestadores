@extends('layouts.modal', [
    'title' => $empresa->razao_social,
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-3">
        <dt class="col-sm-3">CNPJ</dt>
        <dd class="col-sm-9">{{ $empresa->cnpj_formatado }}</dd>

        <dt class="col-sm-3">Situação</dt>
        <dd class="col-sm-9">@include('cadastros._situacao', ['ativo' => $empresa->ativo])</dd>

        <dt class="col-sm-3">Inscrições</dt>
        <dd class="col-sm-9">
            Estadual: {{ $empresa->inscricao_estadual ?? '—' }} · Municipal: {{ $empresa->inscricao_municipal ?? '—' }}
        </dd>

        <dt class="col-sm-3">Telefone</dt>
        <dd class="col-sm-9">{{ $empresa->telefone ?? '—' }}</dd>

        <dt class="col-sm-3">Endereço</dt>
        <dd class="col-sm-9">{{ $empresa->endereco?->formatado() ?: '—' }}</dd>

        @include('cadastros._rodape-uau', ['registro' => $empresa])
    </dl>

    <h6 class="text-semibold">Obras ({{ $empresa->obras->count() }})</h6>
    @if ($empresa->obras->isEmpty())
        <p class="text-muted mb-0">Nenhuma obra importada desta empresa.</p>
    @else
        <div style="max-height: 320px; overflow: auto">
            <table class="table table-sm mb-0">
                <thead>
                    <tr><th>Código</th><th>Descrição</th><th>Status UAU</th><th>Situação</th></tr>
                </thead>
                <tbody>
                    @foreach ($empresa->obras as $obra)
                        <tr>
                            <td>{{ $obra->uau_codigo }}</td>
                            <td>{{ $obra->descricao }}</td>
                            <td>{{ $obra->statusRotulo() }}</td>
                            <td>@include('cadastros._situacao', ['ativo' => $obra->ativo])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

@extends('layouts.modal', [
    'title' => 'Detalhes da chamada',
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-3">
        <dt class="col-sm-3">Data</dt>
        <dd class="col-sm-9">{{ $registro->executado_em->format('d/m/Y H:i:s.v') }}</dd>

        <dt class="col-sm-3">Operação</dt>
        <dd class="col-sm-9">
            {{ \App\Integrations\Uau\OperacaoUau::tryFrom($registro->operacao)?->rotulo() ?? $registro->operacao }}
            · {{ $registro->direcao->rotulo() }}
        </dd>

        <dt class="col-sm-3">Rota</dt>
        <dd class="col-sm-9"><code>{{ $registro->rota }}</code></dd>

        <dt class="col-sm-3">Objeto</dt>
        <dd class="col-sm-9">{{ $registro->descreverEntidade() ?? '—' }}</dd>

        <dt class="col-sm-3">Situação</dt>
        <dd class="col-sm-9">
            @include('integracoes.situacao', ['log' => $registro])
            @if ($resolvidaPor)
                <span class="small text-muted">pela chamada de {{ $resolvidaPor->executado_em->format('d/m/Y H:i:s') }}</span>
            @endif
        </dd>

        <dt class="col-sm-3">HTTP · duração</dt>
        <dd class="col-sm-9">{{ $registro->http_status ?? '—' }} · {{ $registro->duracao_ms === null ? '—' : $registro->duracao_ms.' ms' }}</dd>

        <dt class="col-sm-3">Tentativa</dt>
        <dd class="col-sm-9">{{ $registro->tentativas }}</dd>

        <dt class="col-sm-3">Chave</dt>
        <dd class="col-sm-9">{{ $registro->chave_idempotencia ?? '—' }}</dd>

        <dt class="col-sm-3">Correlação</dt>
        <dd class="col-sm-9"><code>{{ $registro->correlacao ?? '—' }}</code></dd>

        @if ($registro->erro)
            <dt class="col-sm-3">Erro</dt>
            <dd class="col-sm-9 text-danger">{{ $registro->erro }}</dd>
        @endif
    </dl>

    {{-- Gravados já sem segredos: o Sanitizador da fatia 4 troca senha, token e dados bancários por [oculto]. --}}
    @foreach (['payload' => 'Enviado', 'resposta' => 'Recebido'] as $campo => $titulo)
        <h6 class="text-semibold">{{ $titulo }}</h6>
        @if ($registro->{$campo} === null)
            <p class="text-muted">—</p>
        @else
            <pre class="bg-body-tertiary border rounded p-2 small" style="max-height: 300px; overflow: auto">{{ json_encode($registro->{$campo}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        @endif
    @endforeach
@endsection

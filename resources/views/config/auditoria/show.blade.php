@extends('layouts.modal', [
    'title' => 'Detalhes da auditoria',
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-3">
        <dt class="col-sm-3">Data</dt>
        <dd class="col-sm-9">{{ $registro->created_at->format('d/m/Y H:i:s') }}</dd>

        <dt class="col-sm-3">Usuário</dt>
        <dd class="col-sm-9">{{ \App\Support\Auditoria::descreverAutor($registro) }}</dd>

        <dt class="col-sm-3">Ação</dt>
        <dd class="col-sm-9">{{ \App\Support\Auditoria::rotulo($registro->event) }}</dd>

        <dt class="col-sm-3">Objeto</dt>
        <dd class="col-sm-9">{{ \App\Support\Auditoria::descreverObjeto($registro) ?? '—' }}</dd>

        <dt class="col-sm-3">IP</dt>
        <dd class="col-sm-9">{{ $registro->properties['ip'] ?? '—' }}</dd>
    </dl>

    @if ($alteracoes)
        <h6 class="text-semibold">Alterações</h6>
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Antes</th>
                    <th>Depois</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($alteracoes as $linha)
                    <tr>
                        <td>{{ $linha['campo'] }}</td>
                        <td>{{ $linha['antes'] }}</td>
                        <td>{{ $linha['depois'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (isset($registro->properties['antes']) || isset($registro->properties['depois']))
        <h6 class="text-semibold">Perfil</h6>
        <p>
            {{ implode(', ', $registro->properties['antes'] ?? []) ?: '—' }}
            <i class="bi bi-arrow-right mx-1"></i>
            {{ implode(', ', $registro->properties['depois'] ?? []) ?: '—' }}
        </p>
    @endif

    @foreach (['incluidas' => 'Permissões incluídas', 'removidas' => 'Permissões removidas'] as $chave => $titulo)
        @if (! empty($registro->properties[$chave]))
            <h6 class="text-semibold">{{ $titulo }}</h6>
            <ul>
                @foreach ($registro->properties[$chave] as $permissao)
                    <li>{{ $permissao }}</li>
                @endforeach
            </ul>
        @endif
    @endforeach
@endsection

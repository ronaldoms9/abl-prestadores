@extends('layouts.app', ['title' => 'Integrações'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="card shadow mb-3" id="sincronizacoes">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h3 class="card-title mb-0">Sincronizações com o UAU</h3>
                            <span class="text-muted small">
                                Todo dia às {{ config('uau.sincronizacao.horario') }}. "Concluída" quer dizer empresas e obras atualizadas
                                e as consultas de pessoas enviadas para a fila; cada consulta aparece na lista abaixo.
                            </span>
                        </div>
                        @permission('integracao-sincronizar')
                            <button type="button" class="btn btn-primary btn-sm" id="sincronizar-agora" @disabled($emAndamento)
                                    onclick="postAction('{{ route('integracao.sincronizar') }}', 'Disparar agora a sincronização completa com o UAU?').then(ok => ok && setTimeout(() => location.reload(), 1500))">
                                <i class="bi bi-arrow-repeat"></i> {{ $emAndamento ? 'Sincronização em andamento' : 'Sincronizar agora' }}
                            </button>
                        @endpermission
                    </div>
                    <div class="card-body pt-0">
                        @if ($sincronizacoes->isEmpty())
                            <p class="text-muted mb-0">Nenhuma sincronização registrada ainda.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr><th>Origem</th><th>Pedida por</th><th>Início</th><th>Fim</th><th>Situação</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($sincronizacoes as $sincronizacao)
                                            <tr>
                                                <td>{{ $sincronizacao->origem->rotulo() }}</td>
                                                <td>{{ $sincronizacao->criadoPor?->name ?? '—' }}</td>
                                                <td class="text-nowrap">{{ $sincronizacao->iniciada_em->format('d/m/Y H:i') }}</td>
                                                <td class="text-nowrap">{{ $sincronizacao->concluida_em?->format('d/m/Y H:i') ?? '—' }}</td>
                                                <td>
                                                    <span class="badge text-bg-{{ match ($sincronizacao->status) {
                                                        \App\Enums\StatusSincronizacao::Concluida => 'success',
                                                        \App\Enums\StatusSincronizacao::Falhou => 'danger',
                                                        default => 'info',
                                                    } }}">{{ $sincronizacao->status->rotulo() }}</span>
                                                    @if ($sincronizacao->erro)
                                                        <span class="small text-danger ms-1">{{ $sincronizacao->erro }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header border-0">
                        <form id="filtros-integracao" class="row g-2 align-items-end" onsubmit="return false">
                            <div class="col-sm-6 col-lg-2">
                                <label for="de" class="small text-semibold">De</label>
                                <input id="de" type="date" name="de" value="{{ $filtros['de'] }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <label for="ate" class="small text-semibold">Até</label>
                                <input id="ate" type="date" name="ate" value="{{ $filtros['ate'] }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="situacao" class="small text-semibold">Situação</label>
                                <select id="situacao" name="situacao" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    @foreach ($situacoes as $valor => $rotulo)
                                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <label for="operacao" class="small text-semibold">Operação</label>
                                <select id="operacao" name="operacao" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    @foreach ($operacoes as $operacao)
                                        <option value="{{ $operacao->value }}" @selected($filtros['operacao'] === $operacao->value)>{{ $operacao->rotulo() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <label for="direcao" class="small text-semibold">Direção</label>
                                <select id="direcao" name="direcao" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    @foreach ($direcoes as $direcao)
                                        <option value="{{ $direcao->value }}" @selected($filtros['direcao'] === $direcao->value)>{{ $direcao->rotulo() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive pr-2 pl-2">
                            {!! $html->table(['style' => 'width: 100%'], true) !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    {!! $html->scripts() !!}
    <script>
        $('#filtros-integracao').on('change', () => $('#dataTableBuilder').DataTable().ajax.reload());
    </script>
@endpush

@extends('layouts.app', ['title' => 'Resultado da carga'])

@php
    $processadas = $resumo['linhas'] - $resumo['nao_processadas'];
@endphp

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="card shadow mb-3">
                    <div class="card-header border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h3 class="card-title mb-0">{{ $importacao->nome_original }}</h3>
                            <span class="text-muted small">
                                {{ $importacao->created_at->format('d/m/Y H:i') }} · {{ $importacao->criadoPor?->name ?? '—' }}
                                · {{ $resumo['linhas'] }} linhas
                            </span>
                        </div>
                        <div>
                            <span class="badge text-bg-{{ match ($importacao->status) {
                                \App\Enums\StatusImportacao::Concluida => 'success',
                                \App\Enums\StatusImportacao::Falhou => 'danger',
                                default => 'secondary',
                            } }}">{{ $importacao->status->rotulo() }}</span>
                            <a href="{{ route('importacao-pessoa.index') }}" class="btn btn-link btn-sm">Voltar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if ($importacao->emAndamento())
                            <div class="alert alert-info py-2" id="andamento">
                                Processando: {{ $processadas }} de {{ $resumo['linhas'] }} linhas. A página se atualiza sozinha.
                            </div>
                        @elseif ($importacao->erro)
                            <div class="alert alert-danger py-2">
                                A carga parou: {{ $importacao->erro }}
                                @if ($resumo['nao_processadas'] > 0)
                                    ({{ $resumo['nao_processadas'] }} linhas não processadas)
                                @endif
                            </div>
                        @endif

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr><th>Resultado</th><th class="text-end">CPF</th><th class="text-end">CNPJ</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($resultados as $resultado)
                                            <tr>
                                                <td>{{ $resultado->rotulo() }}</td>
                                                <td class="text-end">{{ $resumo['pf'][$resultado->value] ?? 0 }}</td>
                                                <td class="text-end">{{ $resumo['pj'][$resultado->value] ?? 0 }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-lg-6">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        <tr><td>Vínculos sugeridos nesta carga</td><td class="text-end">{{ $resumo['vinculos'] }}</td></tr>
                                        <tr><td>Linhas sem CPF nem CNPJ</td><td class="text-end">{{ $resumo['sem_documento'] }}</td></tr>
                                        <tr><td>Linhas não processadas</td><td class="text-end">{{ $resumo['nao_processadas'] }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-header border-0">
                        <form id="filtros-linhas" class="row g-2 align-items-end" onsubmit="return false">
                            <div class="col-sm-6 col-lg-3">
                                <label for="problemas" class="small text-semibold">Linhas</label>
                                <select id="problemas" name="problemas" class="form-select form-select-sm">
                                    <option value="1">Só com problema</option>
                                    <option value="0">Todas</option>
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
        $('#filtros-linhas').on('change', () => $('#dataTableBuilder').DataTable().ajax.reload());
        @if ($importacao->emAndamento())
            setTimeout(() => window.location.reload(), 10000);
        @endif
    </script>
@endpush

@extends('layouts.app', ['title' => 'Integrações'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
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

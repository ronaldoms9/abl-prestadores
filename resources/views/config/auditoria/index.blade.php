@extends('layouts.app', ['title' => 'Auditoria'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <form id="filtros-auditoria" class="row g-2 align-items-end" onsubmit="return false">
                            <div class="col-sm-6 col-lg-2">
                                <label for="de" class="small text-semibold">De</label>
                                <input id="de" type="date" name="de" value="{{ $dePadrao }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <label for="ate" class="small text-semibold">Até</label>
                                <input id="ate" type="date" name="ate" value="{{ $atePadrao }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="usuario" class="small text-semibold">Usuário</label>
                                <select id="usuario" name="usuario" class="form-select form-select-sm">
                                    <option value="">Todos</option>
                                    @foreach ($usuarios as $usuario)
                                        <option value="{{ $usuario->id }}">
                                            {{ $usuario->name }}{{ $usuario->trashed() ? ' (excluído)' : (! $usuario->ativo ? ' (inativo)' : '') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <label for="acao" class="small text-semibold">Ação</label>
                                <select id="acao" name="acao" class="form-select form-select-sm">
                                    <option value="">Todas</option>
                                    @foreach ($acoes as $valor => $rotulo)
                                        <option value="{{ $valor }}">{{ $rotulo }}</option>
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
        $('#filtros-auditoria').on('change', () => $('#dataTableBuilder').DataTable().ajax.reload());
    </script>
@endpush

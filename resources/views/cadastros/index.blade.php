@extends('layouts.app', ['title' => $titulo])

{{-- Lista de consulta comum às telas da fatia 7: busca, situação e filtros extras da tela. --}}
@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <form id="filtros-cadastro" class="row g-2 align-items-end" onsubmit="return false">
                            <div class="col-sm-6 col-lg-4">
                                <label for="busca" class="small text-semibold">Buscar</label>
                                <input id="busca" type="search" name="busca" class="form-control form-control-sm"
                                       placeholder="{{ $placeholder }}" autocomplete="off">
                            </div>
                            <div class="col-sm-6 col-lg-2">
                                <label for="situacao" class="small text-semibold">Situação</label>
                                <select id="situacao" name="situacao" class="form-select form-select-sm">
                                    @foreach ($situacoes as $valor => $rotulo)
                                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @isset($filtrosExtras)
                                @include($filtrosExtras)
                            @endisset
                        </form>
                        <p class="text-muted small mb-0 mt-2">
                            Somente consulta: estes cadastros vêm do UAU e se atualizam na sincronização diária.
                        </p>
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
        (() => {
            const recarregar = () => $('#dataTableBuilder').DataTable().ajax.reload();
            let espera;
            $('#filtros-cadastro').on('change', recarregar);
            $('#busca').on('input', () => { clearTimeout(espera); espera = setTimeout(recarregar, 400); });
        })();
    </script>
@endpush

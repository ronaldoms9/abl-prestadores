@extends('layouts.app', ['title' => 'Usuários'])

@section('content')
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col">
                                <form id="filtros-usuarios" class="d-flex align-items-center gap-2" onsubmit="return false">
                                    <label for="situacao" class="small text-semibold mb-0">Situação</label>
                                    <select id="situacao" name="situacao" class="form-select form-select-sm w-auto">
                                        <option value="">Todos</option>
                                        <option value="ativos">Ativos</option>
                                        <option value="inativos">Inativos</option>
                                    </select>
                                </form>
                            </div>
                            <div class="col text-end">
                                <a href="javascript:requestIntoModal('{{ route('user.form') }}')"
                                    class="btn btn-outline-primary btn-md">Adicionar</a>
                            </div>
                        </div>
                    </div>

                    <div class=" card-body">
                        <div class=" table-responsive pr-2 pl-2">
                            {!! $html->table(['style' => 'width: 100%'], true) !!}

                        </div>
                    </div>
                    <div class="card-footer py-4">
                        <nav class="d-flex justify-content-end" aria-label="...">

                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    {!! $html->scripts() !!}
    <script>
        $('#filtros-usuarios').on('change', () => $('#dataTableBuilder').DataTable().ajax.reload());
    </script>
@endpush

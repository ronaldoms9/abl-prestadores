@extends('layouts.app', ['title' => 'Usuários'])

@section('content')
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col">
                <div class="card shadow">
                    <div class="card-header border-0">
                        <div class="row align-items-center">
                            <div class="col text-right">
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
@endpush

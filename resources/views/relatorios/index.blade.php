@extends('layouts.app', ['title' => 'Relatórios'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0"><h5 class="mb-0">Relatórios</h5></div>
        <div class="card-body">
            @if ($relatorios->isEmpty())
                <p class="text-muted mb-0">Nenhum relatório disponível para o seu perfil.</p>
            @else
                <div class="row g-3">
                    @foreach ($relatorios as $r)
                        <div class="col-md-6 col-xl-4">
                            <a href="{{ route('relatorio.show', $r->slug()) }}" class="text-decoration-none">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold">{{ $r->titulo() }}</div>
                                    <div class="small text-muted">{{ $r->descricao() }}</div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@extends('layouts.app', ['title' => 'Ajuda'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0"><h5 class="mb-0">Ajuda</h5></div>
        <div class="card-body">
            @if ($manuais === [])
                <p class="text-muted mb-0">Nenhum manual disponível para o seu perfil.</p>
            @else
                <div class="row g-3">
                    @foreach ($manuais as $slug => $titulo)
                        <div class="col-md-6 col-xl-4"><a href="{{ route('ajuda.show', $slug) }}" class="text-decoration-none"><div class="border rounded p-3 h-100 fw-semibold">{{ $titulo }}</div></a></div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

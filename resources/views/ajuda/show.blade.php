@extends('layouts.app', ['title' => $titulo])

@section('content')
<div class="container-fluid">
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card shadow"><div class="card-body p-2">
                <div class="list-group list-group-flush">
                    @foreach ($manuais as $slug => $t)
                        <a href="{{ route('ajuda.show', $slug) }}" class="list-group-item list-group-item-action {{ $slug === $atual ? 'active' : '' }}">{{ $t }}</a>
                    @endforeach
                </div>
            </div></div>
        </div>
        <div class="col-lg-9">
            <div class="card shadow"><div class="card-body manual">{!! $html !!}</div></div>
        </div>
    </div>
</div>
@endsection

@push('js')
<style>
    .manual h1 { font-size: 1.6rem; margin-bottom: 1rem; }
    .manual h2 { font-size: 1.2rem; margin-top: 1.6rem; color: #235A9A; }
    .manual table { width: 100%; margin: .75rem 0; border-collapse: collapse; font-size: .9rem; }
    .manual th, .manual td { border: 1px solid #dee2e6; padding: .35rem .6rem; vertical-align: top; }
    .manual th { background: #eef3f9; }
    .manual code { background: #f1f3f5; padding: 0 .25rem; border-radius: 3px; }
</style>
@endpush

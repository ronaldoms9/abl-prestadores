@extends('layouts.app', ['title' => 'Minhas notas'])

@php
    $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $cores = ['aguardando' => 'secondary', 'enviada' => 'primary', 'divergente' => 'warning', 'aprovada' => 'success', 'recusada' => 'danger'];
@endphp

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0"><h5 class="mb-0">Minhas notas fiscais</h5></div>
        <div class="card-body">
            <p class="text-muted small">Cada mês fechado mostra o valor que a nota deve ter. Envie o PDF e, se houver, o XML da nota; o sistema confere os dados com o seu cadastro.</p>
            @if ($fechamentos->isEmpty())
                <p class="text-muted mb-0">Nenhuma competência fechada para enviar nota ainda.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Competência</th><th class="text-end">Valor esperado</th><th>Situação</th><th>Detalhe</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($fechamentos as $f)
                            @php $nota = $f->nota; $status = $nota?->status ?? \App\Enums\StatusNota::Aguardando; @endphp
                            <tr>
                                <td>{{ substr($f->competencia->ano_mes, 5, 2) }}/{{ substr($f->competencia->ano_mes, 0, 4) }}</td>
                                <td class="text-end">{{ $fmt($f->total_liquido) }}</td>
                                <td><span class="badge text-bg-{{ $cores[$status->value] }}">{{ $status->rotulo() }}</span></td>
                                <td class="small">
                                    @if ($nota?->status === \App\Enums\StatusNota::Recusada)<span class="text-danger">Recusada: {{ $nota->motivo_recusa }}</span>@endif
                                    @if ($nota?->status === \App\Enums\StatusNota::Divergente)@foreach ($nota->divergencias as $d)<div class="text-muted">{{ $d['mensagem'] }}</div>@endforeach @endif
                                    @if ($nota)<span class="text-muted">Nota {{ $nota->numero ?? '—' }}</span>@endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($nota?->pdf_path)<a href="{{ route('portal.nota.arquivo', [$nota->id, 'pdf']) }}" class="small">PDF</a>@endif
                                    @if ($nota?->xml_path)<a href="{{ route('portal.nota.arquivo', [$nota->id, 'xml']) }}" class="small ms-1">XML</a>@endif
                                    @if ($status->aceitaEnvio())
                                        <a href="javascript:requestIntoModal('{{ route('portal.nota.form', $f->id) }}')" class="btn btn-primary btn-sm ms-2">{{ $nota ? 'Reenviar' : 'Enviar nota' }}</a>
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
</div>
@endsection

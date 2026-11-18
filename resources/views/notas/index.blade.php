@extends('layouts.app', ['title' => 'Notas fiscais'])

@php
    $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $cores = ['aguardando' => 'secondary', 'enviada' => 'primary', 'divergente' => 'warning', 'aprovada' => 'success', 'recusada' => 'danger'];
@endphp

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0">
            <h5 class="mb-2">Notas fiscais</h5>
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label for="competencia_id" class="small text-semibold">Competência</label>
                    <select id="competencia_id" name="competencia_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($competencias as $c)<option value="{{ $c->id }}" @selected($competencia?->id === $c->id)>{{ substr($c->ano_mes, 5, 2) }}/{{ substr($c->ano_mes, 0, 4) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-2">
                    <label for="status" class="small text-semibold">Situação</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(($filtros['status'] ?? null) === $s->value)>{{ $s->rotulo() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <label for="obra_id" class="small text-semibold">Obra</label>
                    <select id="obra_id" name="obra_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach ($obras as $o)<option value="{{ $o->id }}" @selected((int) ($filtros['obra_id'] ?? 0) === $o->id)>{{ ($o->codigo ? $o->codigo.' · ' : '').$o->descricao }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-2"><label for="busca" class="small text-semibold">Colaborador</label><input id="busca" type="text" name="busca" value="{{ $filtros['busca'] ?? '' }}" class="form-control form-control-sm" placeholder="Nome"></div>
                <div class="col-sm-2 d-flex gap-2"><button class="btn btn-outline-secondary btn-sm">Filtrar</button>
                    @if ($competencia)<button type="button" class="btn btn-outline-primary btn-sm" id="btn-zip" data-url="{{ route('nota.zip', $competencia->id) }}">Baixar ZIP</button>@endif</div>
            </form>
        </div>
        <div class="card-body">
            @if ($competencia === null)
                <p class="text-muted mb-0">Nenhuma competência aprovada ainda. As notas começam depois da aprovação do fechamento.</p>
            @elseif ($fechamentos->isEmpty())
                <p class="text-muted mb-0">Nenhum colaborador neste filtro.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Colaborador</th><th>Obra</th><th class="text-end">Esperado</th><th>Nota</th><th>Situação</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($fechamentos as $f)
                            @php $nota = $f->nota; $status = $nota?->status ?? \App\Enums\StatusNota::Aguardando; @endphp
                            <tr>
                                <td>{{ $f->colaborador->pessoaFisica->nome }}</td>
                                <td class="small">{{ $f->colaborador->obra?->descricao ?? '—' }}</td>
                                <td class="text-end">{{ $fmt($f->total_liquido) }}</td>
                                <td class="small">@if ($nota){{ $nota->numero ?? '—' }} · {{ $nota->valor_bruto !== null ? $fmt($nota->valor_bruto) : '—' }}@else —@endif</td>
                                <td><span class="badge text-bg-{{ $cores[$status->value] }}">{{ $status->rotulo() }}</span>
                                    @if ($nota?->status === \App\Enums\StatusNota::Divergente)<span class="small text-muted" title="{{ implode(' ', array_column($nota->divergencias ?? [], 'mensagem')) }}">{{ count($nota->divergencias ?? []) }} divergência(s)</span>@endif</td>
                                <td class="text-end text-nowrap">
                                    @if ($nota)<a href="javascript:requestIntoModal('{{ route('nota.show', $nota->id) }}')" class="btn btn-outline-primary btn-sm">Ver</a>@endif
                                    @permission('nota-decidir')
                                        @if ($nota && $nota->status->aguardaDecisao())<a href="javascript:requestIntoModal('{{ route('nota.decidir.form', $nota->id) }}')" class="btn btn-primary btn-sm">Decidir</a>@endif
                                    @endpermission
                                    @permission('nota-enviar')
                                        @if ($status->aceitaEnvio())<a href="javascript:requestIntoModal('{{ route('nota.enviar.form', $f->id) }}')" class="btn btn-outline-secondary btn-sm">Enviar em nome dele</a>@endif
                                    @endpermission
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

@push('js')
<script>
    document.getElementById('btn-zip')?.addEventListener('click', function () {
        axios.post(this.dataset.url, {})
            .then((r) => showNotify('Sucesso', r.data.message))
            .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
    });
</script>
@endpush

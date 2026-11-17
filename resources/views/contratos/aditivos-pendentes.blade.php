@extends('layouts.app', ['title' => 'Aditivos pendentes'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0">
            <h5 class="mb-0">Aditivos pendentes</h5>
            <p class="text-muted small mb-0 mt-1">Rascunhos criados sozinhos quando há reajuste, mudança de cargo ou alteração de benefícios de quem tem contrato em vigor.
                Confira, gere os que estiverem corretos e cancele os que não se aplicam (na ficha do colaborador).</p>
        </div>
        <div class="card-body">
            @if ($rascunhos->isEmpty())
                <p class="text-muted mb-0">Nenhum aditivo pendente.</p>
            @else
                @if ($rascunhos->count() > $limite)
                    <div class="alert alert-info py-2 small">Mostrando os {{ $limite }} mais antigos. Gere esses e volte para ver os demais.</div>
                @endif
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="todos" class="form-check-input" aria-label="Selecionar todos"></th>
                                <th>Colaborador</th><th>Contrato</th><th>Motivo</th><th>A partir de</th><th>Detalhe</th><th>Modelo</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($rascunhos->take($limite) as $rascunho)
                            @php
                                $d = $rascunho->dados ?? [];
                                $fmt = fn ($c) => \App\Support\Dinheiro::formatar((int) $c);
                                $detalhe = match ($rascunho->motivo) {
                                    \App\Enums\MotivoAditivo::Reajuste => (isset($d['valor_anterior']) ? $fmt($d['valor_anterior']).' → ' : 'novo valor ').$fmt($d['valor_novo'] ?? 0),
                                    \App\Enums\MotivoAditivo::MudancaCargo => ($d['cargo_anterior'] ?? '—').' → '.($d['cargo_novo'] ?? '—'),
                                    \App\Enums\MotivoAditivo::Beneficios => $d['beneficios_alteracao'] ?? '—',
                                    default => '—',
                                };
                            @endphp
                            <tr>
                                <td><input type="checkbox" class="form-check-input sel" value="{{ $rascunho->id }}" aria-label="Selecionar {{ $rascunho->colaborador->pessoaFisica->nome }}"></td>
                                <td>{{ $rascunho->colaborador->pessoaFisica->nome }}</td>
                                <td>{{ $rascunho->pai?->numero ?? '—' }}</td>
                                <td>{{ $rascunho->motivo?->rotulo() ?? '—' }}</td>
                                <td class="text-nowrap">{{ $rascunho->vigencia_inicio?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $detalhe }}</td>
                                <td class="text-muted small">{{ $rascunho->modelo->nome }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" id="btn-gerar" class="btn btn-primary" disabled>Gerar selecionados</button>
            @endif
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    (() => {
        const sel = () => [...document.querySelectorAll('.sel:checked')].map((c) => c.value);
        const btn = document.getElementById('btn-gerar');
        if (!btn) return;
        const atualizar = () => { btn.disabled = sel().length === 0; };
        document.getElementById('todos').addEventListener('change', (e) => { document.querySelectorAll('.sel').forEach((c) => { c.checked = e.target.checked; }); atualizar(); });
        document.querySelectorAll('.sel').forEach((c) => c.addEventListener('change', atualizar));
        btn.addEventListener('click', () => {
            if (!confirm('Gerar ' + sel().length + ' aditivo(s)? Depois de gerados eles não podem ser alterados.')) return;
            const dados = new FormData();
            sel().forEach((id) => dados.append('ids[]', id));
            axios.post(@json(route('aditivo.pendentes.gerar')), dados, { validateStatus: (s) => s === 200 || s === 207 }).then((r) => {
                showNotify(r.data.success ? 'Sucesso' : 'Atenção', r.data.message, r.data.success ? 'success' : 'error');
                setTimeout(() => location.reload(), r.data.success ? 900 : 4000);
            }).catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
        });
    })();
</script>
@endpush

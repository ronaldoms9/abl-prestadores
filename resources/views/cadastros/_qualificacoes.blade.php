{{--
    Qualificações de um colaborador (E2, fatia 7).
    Recebe: $colaborador (com qualificacoes.tipo), $tiposQualificacao (null quando não pode
    registrar), $situacaoQualificacoes (ausentes, vencidas e a vencer das obrigatórias) e $recarregarUrl.
--}}
@php $hoje = now(); @endphp
<h6 class="text-semibold mt-3">Qualificações</h6>

@php $s = $situacaoQualificacoes; @endphp
@if ($s && ($s['ausentes']->isNotEmpty() || $s['vencidas']->isNotEmpty()))
    <div class="alert alert-danger py-2 small">
        @if ($s['ausentes']->isNotEmpty())<div><strong>Obrigatórias ausentes:</strong> {{ $s['ausentes']->pluck('nome')->implode(', ') }}.</div>@endif
        @if ($s['vencidas']->isNotEmpty())<div><strong>Obrigatórias vencidas:</strong> {{ $s['vencidas']->map(fn ($q) => $q->tipo->nome.' ('.$q->data_validade->format('d/m/Y').')')->implode(', ') }}.</div>@endif
    </div>
@endif

@if ($colaborador->qualificacoes->isEmpty())
    <p class="text-muted">Nenhuma qualificação registrada.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Qualificação</th><th>Concluída em</th><th>Validade</th><th>Instituição</th><th>Certificado</th><th></th></tr></thead>
            <tbody>
            @foreach ($colaborador->qualificacoes->sortByDesc('data_conclusao') as $q)
                <tr>
                    <td>{{ $q->tipo->nome }}</td>
                    <td>{{ $q->data_conclusao->format('d/m/Y') }}</td>
                    <td class="text-nowrap">
                        @if ($q->data_validade)
                            {{ $q->data_validade->format('d/m/Y') }}
                            @if ($q->data_validade->lt($hoje->copy()->startOfDay()))<span class="badge text-bg-danger">Vencida</span>@endif
                        @else
                            não vence
                        @endif
                    </td>
                    <td>{{ $q->instituicao ?? '—' }}</td>
                    <td>@if ($q->certificado_path)<a href="{{ route('qualificacao.certificado', $q->id) }}">{{ $q->certificado_nome ?? 'Baixar' }}</a>@else —@endif</td>
                    <td class="text-end">
                        @permission('qualificacao-edit')
                            <button type="button" class="btn btn-link btn-sm text-danger p-0 acao-icone" data-excluir-qualificacao="{{ route('qualificacao.excluir', $q->id) }}" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></button>
                        @endpermission
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($tiposQualificacao !== null)
    @if ($tiposQualificacao->isEmpty())
        <p class="text-muted small">Nenhum tipo de qualificação ativo. Cadastre em Qualificações › Tipos.</p>
    @else
        <form id="form-qualificacao" class="row g-2 align-items-end mb-3" action="{{ route('qualificacao.registrar', $colaborador->id) }}" enctype="multipart/form-data">
            <div class="col-md-4">
                <label for="q-tipo" class="small text-semibold">Qualificação</label>
                <select id="q-tipo" name="qualificacao_tipo_id" class="form-select form-select-sm" required>
                    <option value="">Selecione</option>
                    @foreach ($tiposQualificacao as $tipo)
                        <option value="{{ $tipo->id }}" data-meses="{{ $tipo->validade_meses }}">{{ $tipo->nome }}{{ $tipo->validade_meses ? " ({$tipo->validade_meses} meses)" : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="q-conclusao" class="small text-semibold">Concluída em</label>
                <input id="q-conclusao" type="date" name="data_conclusao" max="{{ $hoje->toDateString() }}" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-5">
                <label for="q-instituicao" class="small text-semibold">Instituição</label>
                <input id="q-instituicao" type="text" name="instituicao" maxlength="255" class="form-control form-control-sm">
            </div>
            <div class="col-md-7">
                <label for="q-certificado" class="small text-semibold">Certificado (PDF, JPG ou PNG, opcional)</label>
                <input id="q-certificado" type="file" name="certificado" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 small text-muted" id="q-validade-previa"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100">Registrar</button></div>
        </form>
    @endif
@endif

@push('scripts')
<script>
    (() => {
        const recarregar = () => requestIntoModal(@json($recarregarUrl));
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const form = document.getElementById('form-qualificacao');
        if (form) {
            const tipo = document.getElementById('q-tipo');
            const conclusao = document.getElementById('q-conclusao');
            const previa = document.getElementById('q-validade-previa');
            const atualizar = () => {
                const meses = parseInt(tipo.selectedOptions[0]?.dataset.meses || '', 10);
                if (!meses || !conclusao.value) { previa.textContent = meses ? '' : (tipo.value ? 'Não vence.' : ''); return; }
                const [a, m, d] = conclusao.value.split('-').map(Number);
                const alvo = new Date(Date.UTC(a, m - 1 + meses, 1));
                const ultimo = new Date(Date.UTC(alvo.getUTCFullYear(), alvo.getUTCMonth() + 1, 0)).getUTCDate();
                alvo.setUTCDate(Math.min(d, ultimo));
                previa.textContent = 'Vale até ' + alvo.toLocaleDateString('pt-BR', {timeZone: 'UTC'}) + '.';
            };
            tipo.addEventListener('change', atualizar);
            conclusao.addEventListener('input', atualizar);
            form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(form.action, new FormData(form)).then(() => { showNotify('Sucesso', 'Qualificação registrada.'); recarregar(); }).catch(falha);
            });
        }
        document.querySelectorAll('[data-excluir-qualificacao]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm('Excluir esta qualificação?')) return;
            axios.delete(b.dataset.excluirQualificacao).then(() => { showNotify('Sucesso', 'Qualificação excluída.'); recarregar(); }).catch(falha);
        }));
    })();
</script>
@endpush

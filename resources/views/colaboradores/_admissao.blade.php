{{-- Checklist de admissão (E3, fatia 5). Espera $colaborador (com checklistAdmissao.concluidoPor), $admissaoAutomaticos e $recarregarUrl. --}}
@php $linhas = $colaborador->checklistAdmissao; $pre = $colaborador->status === \App\Enums\StatusColaborador::PreAdmissao; @endphp
@if ($pre || $linhas->isNotEmpty())
    <h6 class="text-semibold mt-3">Admissão</h6>

    @if ($linhas->isEmpty())
        <p class="text-muted">A admissão ainda não foi iniciada.</p>
        <button type="button" class="btn btn-primary btn-sm mb-3" data-admissao-iniciar="{{ route('admissao.iniciar', $colaborador->id) }}">Iniciar admissão</button>
    @else
        <ul class="list-unstyled mb-2">
            @foreach ($linhas as $linha)
                @php
                    $auto = $linha->chave !== null ? ($admissaoAutomaticos[$linha->chave] ?? null) : null;
                    $ok = $pre && $auto !== null ? $auto['ok'] : $linha->concluido;
                @endphp
                <li class="d-flex align-items-start gap-2 mb-1">
                    @if ($linha->chave !== null || ! $pre)
                        <i class="bi {{ $ok ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} mt-1" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ $ok ? 'Concluído' : 'Pendente' }}</span>
                    @else
                        <input type="checkbox" class="form-check-input mt-1" data-admissao-item="{{ route('admissao.marcar', $linha->id) }}" @checked($linha->concluido) aria-label="{{ $linha->item }}">
                    @endif
                    <div>
                        {{ $linha->item }}
                        @unless ($linha->obrigatorio)<span class="badge text-bg-light">opcional</span>@endunless
                        @if ($linha->chave !== null)<span class="badge text-bg-secondary">automático</span>@endif
                        @if ($pre && $auto !== null && ! $auto['ok'] && $auto['detalhe'])
                            <div class="text-muted small">{{ $auto['detalhe'] }}</div>
                        @elseif ($linha->concluido && $linha->chave === null && $linha->concluido_em)
                            <div class="text-muted small">por {{ $linha->concluidoPor?->name ?? '—' }} em {{ $linha->concluido_em->format('d/m/Y H:i') }}</div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($pre)
            <form id="form-admissao" class="row g-2 align-items-end mb-3" action="{{ route('admissao.concluir', $colaborador->id) }}">
                <div class="col-sm-3">
                    <label for="adm-data" class="small text-semibold">Data de admissão</label>
                    <input id="adm-data" type="date" name="data_admissao" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                </div>
                @if (! ($admissaoAutomaticos[\App\Support\Admissao\ItensAutomaticos::REMUNERACAO_DEFINIDA]['ok'] ?? true))
                    <div class="col-sm-4">
                        <label for="adm-rem" class="small text-semibold">Remuneração inicial (R$)</label>
                        <input id="adm-rem" type="number" step="0.01" min="0.01" name="remuneracao_inicial" class="form-control form-control-sm" placeholder="ainda não definida">
                    </div>
                @endif
                <div class="col-sm-3"><button type="submit" class="btn btn-success btn-sm w-100">Concluir admissão</button></div>
            </form>
        @endif
    @endif

    @push('scripts')
    <script>
        (() => {
            const recarregar = () => requestIntoModal(@json($recarregarUrl));
            const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
            document.querySelectorAll('[data-admissao-iniciar]').forEach((b) => b.addEventListener('click', () => {
                axios.post(b.dataset.admissaoIniciar, {}).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
            }));
            document.querySelectorAll('[data-admissao-item]').forEach((c) => c.addEventListener('change', () => {
                axios.post(c.dataset.admissaoItem, { concluido: c.checked ? 1 : 0 }).then(recarregar).catch((e) => { c.checked = !c.checked; falha(e); });
            }));
            const form = document.getElementById('form-admissao');
            if (form) form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                if (!confirm('Concluir a admissão? O colaborador passa a ativo e recebe os benefícios padrão do cargo.')) return;
                axios.post(form.action, new FormData(form)).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
            });
        })();
    </script>
    @endpush
@endif

{{-- Linha do tempo de contratos, aditivos e distrato (E3, fatia 3). Espera $colaborador (com contratos.modelo), $modelosContrato e $recarregarUrl. --}}
@php
    $bases = $colaborador->contratos->where('tipo', \App\Enums\TipoContrato::Contrato)->sortByDesc('id');
    $filhos = $colaborador->contratos->where('tipo', '!=', \App\Enums\TipoContrato::Contrato)->groupBy('contrato_pai_id');
    $orfaos = $colaborador->contratos->where('tipo', '!=', \App\Enums\TipoContrato::Contrato)->whereNull('contrato_pai_id');
    $paisElegiveis = $bases->filter(fn ($c) => in_array($c->status, [\App\Enums\StatusContrato::Gerado, \App\Enums\StatusContrato::Assinado], true));
    $linhas = collect();
    foreach ($bases as $base) {
        $linhas->push([$base, 0]);
        foreach (($filhos[$base->id] ?? collect())->sortBy('id') as $filho) { $linhas->push([$filho, 1]); }
    }
    foreach ($orfaos as $orfao) { $linhas->push([$orfao, 0]); }
    $cores = ['rascunho' => 'secondary', 'gerado' => 'primary', 'assinado' => 'success', 'encerrado' => 'dark'];
@endphp

<h6 class="text-semibold mt-3">Contratos</h6>
@if ($linhas->isEmpty())
    <p class="text-muted">Nenhum contrato, aditivo ou distrato.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Documento</th><th>Vigência</th><th>Situação</th><th class="text-end">Ações</th></tr></thead>
            <tbody>
            @foreach ($linhas as [$contrato, $nivel])
                <tr>
                    <td @style(['padding-left: 1.5rem' => $nivel === 1])>
                        {{ $nivel === 1 ? '↳ ' : '' }}<strong>{{ $contrato->tipo->rotulo() }}</strong>
                        {{ $contrato->numero ?? '(sem número)' }}
                        @if ($contrato->motivo)<span class="text-muted small">· {{ $contrato->motivo->rotulo() }}</span>@endif
                        <div class="text-muted small">{{ $contrato->modelo->nome }} (v{{ $contrato->modelo->versao }})</div>
                    </td>
                    <td class="text-nowrap">
                        {{ $contrato->vigencia_inicio?->format('d/m/Y') ?? '—' }}
                        @if ($contrato->vigencia_fim) a {{ $contrato->vigencia_fim->format('d/m/Y') }}@endif
                    </td>
                    <td><span class="badge text-bg-{{ $cores[$contrato->status->value] }}">{{ $contrato->status->rotulo() }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="javascript:requestIntoModal('{{ route('contrato.previa', $contrato->id) }}')" class="acao-icone text-primary" title="Ver" aria-label="Ver"><i class="bi bi-eye"></i></a>
                        @if ($contrato->tipo === \App\Enums\TipoContrato::Contrato && $contrato->vigencia_fim && in_array($contrato->status, [\App\Enums\StatusContrato::Gerado, \App\Enums\StatusContrato::Assinado], true))
                            @permission('contrato-gerar')
                                <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-contrato-renovar="{{ route('contrato.renovar', $contrato->id) }}">Renovar</button>
                            @endpermission
                        @endif
                        @if ($contrato->status === \App\Enums\StatusContrato::Rascunho)
                            @permission('contrato-gerar')
                                <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-contrato-gerar="{{ route('contrato.gerar', $contrato->id) }}">Gerar</button>
                                <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-2" data-contrato-cancelar="{{ route('contrato.cancelar', $contrato->id) }}">Cancelar</button>
                            @endpermission
                        @else
                            @if ($contrato->pdf_path)
                                <a href="{{ route('contrato.pdf', $contrato->id) }}" class="ms-2 small">PDF</a>
                            @else
                                <span class="text-muted small ms-2">PDF pendente</span>
                                @permission('contrato-gerar')
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1" data-contrato-pdf="{{ route('contrato.pdf.regerar', $contrato->id) }}">gerar de novo</button>
                                @endpermission
                            @endif
                            @if ($contrato->assinado_path)
                                <a href="{{ route('contrato.assinado.baixar', $contrato->id) }}" class="ms-2 small">Assinado</a>
                            @elseif ($contrato->status === \App\Enums\StatusContrato::Gerado)
                                @permission('contrato-assinar')
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-2" data-contrato-assinado="{{ route('contrato.assinado', $contrato->id) }}">Anexar assinado</button>
                                @endpermission
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@permission('contrato-gerar')
    @if ($modelosContrato->isNotEmpty())
        <form id="form-contrato" class="row g-2 align-items-end mb-3" action="{{ route('contrato.store', $colaborador->id) }}">
            <div class="col-sm-4">
                <label for="ctr-modelo" class="small text-semibold">Novo documento (modelo)</label>
                <select id="ctr-modelo" name="contrato_modelo_id" class="form-select form-select-sm" required>
                    <option value="">Selecione</option>
                    @foreach ($modelosContrato as $modelo)
                        <option value="{{ $modelo->id }}" data-tipo="{{ $modelo->tipo->value }}">{{ $modelo->tipo->rotulo() }} · {{ $modelo->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3" id="ctr-bloco-pai" hidden>
                <label for="ctr-pai" class="small text-semibold">Contrato</label>
                <select id="ctr-pai" name="contrato_pai_id" class="form-select form-select-sm">
                    @foreach ($paisElegiveis as $pai)<option value="{{ $pai->id }}">{{ $pai->numero }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-2">
                <label for="ctr-inicio" class="small text-semibold">Início</label>
                <input id="ctr-inicio" type="date" name="vigencia_inicio" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-sm-2">
                <label for="ctr-fim" class="small text-semibold">Fim (opcional)</label>
                <input id="ctr-fim" type="date" name="vigencia_fim" class="form-control form-control-sm">
            </div>
            <div class="col-sm-1"><button type="submit" class="btn btn-primary btn-sm w-100">Criar</button></div>
        </form>
    @endif
@endpermission
<input type="file" id="ctr-arquivo" accept=".pdf,.jpg,.jpeg,.png" hidden>
@permission('contrato-gerar')
    <form id="form-renovar" class="row g-2 align-items-end mb-3" hidden>
        <div class="col-sm-4">
            <label for="ctr-renovar-fim" class="small text-semibold">Prorrogar até (último dia)</label>
            <input id="ctr-renovar-fim" type="date" name="vigencia_fim" class="form-control form-control-sm" required>
        </div>
        <div class="col-sm-3"><button type="submit" class="btn btn-primary btn-sm w-100">Criar aditivo de prorrogação</button></div>
    </form>
@endpermission

@push('scripts')
<script>
    (() => {
        const recarregar = () => requestIntoModal(@json($recarregarUrl));
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const ok = (r) => { showNotify('Sucesso', r.data.message); recarregar(); };

        const form = document.getElementById('form-contrato');
        if (form) {
            const modelo = document.getElementById('ctr-modelo');
            modelo.addEventListener('change', () => {
                const tipo = modelo.selectedOptions[0]?.dataset.tipo;
                document.getElementById('ctr-bloco-pai').hidden = !(tipo === 'aditivo' || tipo === 'distrato');
            });
            form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(form.action, new FormData(form)).then((r) => {
                    showNotify('Sucesso', r.data.message);
                    requestIntoModal(@json(route('contrato.previa', '__ID__')).replace('__ID__', r.data.data.id));
                }).catch(falha);
            });
        }

        document.querySelectorAll('[data-contrato-gerar]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm('Gerar o documento? Depois de gerado ele não pode ser alterado.')) return;
            axios.post(b.dataset.contratoGerar, {}).then(ok).catch(falha);
        }));
        document.querySelectorAll('[data-contrato-cancelar]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm('Cancelar este rascunho?')) return;
            axios.delete(b.dataset.contratoCancelar).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
        }));
        document.querySelectorAll('[data-contrato-pdf]').forEach((b) => b.addEventListener('click', () => {
            axios.post(b.dataset.contratoPdf, {}).then(ok).catch(falha);
        }));

        const renovar = document.getElementById('form-renovar');
        if (renovar) {
            let destinoRenovar = null;
            document.querySelectorAll('[data-contrato-renovar]').forEach((b) => b.addEventListener('click', () => { destinoRenovar = b.dataset.contratoRenovar; renovar.hidden = false; renovar.scrollIntoView({ block: 'nearest' }); }));
            renovar.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(destinoRenovar, new FormData(renovar)).then((r) => {
                    showNotify('Sucesso', r.data.message);
                    requestIntoModal(@json(route('contrato.previa', '__ID__')).replace('__ID__', r.data.data.id));
                }).catch(falha);
            });
        }

        const arquivo = document.getElementById('ctr-arquivo');
        let destino = null;
        document.querySelectorAll('[data-contrato-assinado]').forEach((b) => b.addEventListener('click', () => { destino = b.dataset.contratoAssinado; arquivo.value = ''; arquivo.click(); }));
        arquivo.addEventListener('change', () => {
            if (!arquivo.files.length || !destino) return;
            const dados = new FormData();
            dados.append('arquivo', arquivo.files[0]);
            axios.post(destino, dados).then(ok).catch(falha);
        });
    })();
</script>
@endpush

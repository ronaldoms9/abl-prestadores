{{--
    Documentos de uma PF, PJ ou colaborador (E2, fatia 5).
    Recebe: $donoApelido, $donoId, $documentos (com tipoDocumento), $tiposDocumento (null quando
    não pode anexar), $recarregarUrl e, no colaborador, $vencidos (o que está vencido entre os
    documentos dele, da PF e da PJ).
--}}
@php $hoje = now(); $dias = (int) config('cadastros.alertas.documentos_dias'); @endphp
<h6 class="text-semibold mt-3">Documentos</h6>

@if (! empty($vencidos) && $vencidos->isNotEmpty())
    <div class="alert alert-danger py-2 small">
        <strong>{{ $vencidos->count() }} documento(s) vencido(s):</strong>
        {{ $vencidos->map(fn ($d) => $d->tipo.' ('.$d->validade->format('d/m/Y').')')->implode(', ') }}.
    </div>
@endif

@if ($documentos->isEmpty())
    <p class="text-muted">Nenhum documento anexado.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Tipo</th><th>Validade</th><th>Arquivo</th><th>Observação</th><th></th></tr></thead>
            <tbody>
            @foreach ($documentos as $documento)
                @php $situacao = $documento->situacaoEm($hoje, $dias); @endphp
                <tr>
                    <td>{{ $documento->tipo }}</td>
                    <td class="text-nowrap">
                        @if ($documento->validade)
                            {{ $documento->validade->format('d/m/Y') }}
                            @if ($situacao === \App\Models\Documento::VENCIDO)<span class="badge text-bg-danger">Vencido</span>
                            @elseif ($situacao === \App\Models\Documento::A_VENCER)<span class="badge text-bg-warning">Vence em breve</span>@endif
                        @else
                            —
                        @endif
                    </td>
                    <td><a href="{{ route('documento.baixar', $documento->id) }}">{{ $documento->arquivo_nome ?? 'Baixar' }}</a></td>
                    <td class="text-muted">{{ $documento->observacao ?? '' }}</td>
                    <td class="text-end">
                        @permission('documento-edit')
                            <button type="button" class="btn btn-link btn-sm text-danger p-0" data-excluir-documento="{{ route('documento.excluir', $documento->id) }}">Excluir</button>
                        @endpermission
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($tiposDocumento !== null)
    @if ($tiposDocumento->isEmpty())
        <p class="text-muted small">Nenhum tipo de documento serve a este cadastro. Cadastre um em Configurações › Tipos de documento.</p>
    @else
        <form id="form-documento" class="row g-2 align-items-end mb-3" action="{{ route('documento.store', [$donoApelido, $donoId]) }}" enctype="multipart/form-data">
            <div class="col-md-4">
                <label for="doc-tipo" class="small text-semibold">Tipo</label>
                <select id="doc-tipo" name="documento_tipo_id" class="form-select form-select-sm" required>
                    <option value="">Selecione</option>
                    @foreach ($tiposDocumento as $tipo)
                        <option value="{{ $tipo->id }}" data-validade="{{ $tipo->exige_validade ? 1 : 0 }}">{{ $tipo->nome }}{{ $tipo->exige_validade ? ' (com validade)' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="doc-validade" class="small text-semibold">Validade</label>
                <input id="doc-validade" type="date" name="validade" class="form-control form-control-sm">
            </div>
            <div class="col-md-5">
                <label for="doc-arquivo" class="small text-semibold">Arquivo (PDF, JPG ou PNG, até {{ intdiv(config('cadastros.documentos.tamanho_maximo_kb'), 1024) }} MB)</label>
                <input id="doc-arquivo" type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-9">
                <label for="doc-obs" class="small text-semibold">Observação</label>
                <input id="doc-obs" type="text" name="observacao" maxlength="500" class="form-control form-control-sm">
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100">Anexar</button></div>
        </form>
    @endif
@endif

@push('scripts')
<script>
    (() => {
        const recarregar = () => requestIntoModal(@json($recarregarUrl));
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const form = document.getElementById('form-documento');
        if (form) {
            const tipo = document.getElementById('doc-tipo');
            const validade = document.getElementById('doc-validade');
            tipo.addEventListener('change', () => { validade.required = tipo.selectedOptions[0]?.dataset.validade === '1'; });
            form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(form.action, new FormData(form)).then(() => { showNotify('Sucesso', 'Documento anexado.'); recarregar(); }).catch(falha);
            });
        }
        document.querySelectorAll('[data-excluir-documento]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm('Excluir este documento?')) return;
            axios.delete(b.dataset.excluirDocumento).then(() => { showNotify('Sucesso', 'Documento excluído.'); recarregar(); }).catch(falha);
        }));
    })();
</script>
@endpush

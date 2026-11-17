{{-- Benefícios do colaborador (E3, fatia 8). Espera $colaborador (com beneficios.beneficio), $beneficiosAtuais e $beneficiosDisponiveis. --}}
@php $fmt = fn ($centavos) => \App\Support\Dinheiro::formatar($centavos); @endphp
<h6 class="text-semibold mt-3">Benefícios</h6>
@if ($colaborador->beneficios->isEmpty())
    <p class="text-muted">Sem benefícios vinculados.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Benefício</th><th>De</th><th>Até</th><th class="text-end">Valor</th><th></th></tr></thead>
            <tbody>
            @foreach ($colaborador->beneficios as $vinculo)
                @php $atual = $beneficiosAtuais->get($vinculo->id); @endphp
                <tr>
                    <td>{{ $vinculo->beneficio->nome }} <span class="text-muted small">({{ $vinculo->beneficio->tipo_calculo->rotulo() }})</span></td>
                    <td>{{ $vinculo->inicio->format('d/m/Y') }}</td>
                    <td>{{ $vinculo->fim ? $vinculo->fim->copy()->subDay()->format('d/m/Y') : 'atual' }}</td>
                    <td class="text-end">
                        @if ($vinculo->valor_personalizado !== null)
                            {{ $fmt(\App\Support\Dinheiro::centavos($vinculo->valor_personalizado)) }} <span class="text-muted small">(personalizado)</span>
                        @elseif ($atual && $atual['valor'] !== null)
                            {{ $fmt($atual['valor']) }} <span class="text-muted small">(tabela)</span>
                        @else
                            <span class="text-muted">tabela vigente</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @permission('colaborador-beneficio-edit')
                            @if (! $vinculo->fim)
                                <button type="button" class="btn btn-link btn-sm text-danger p-0"
                                        data-encerrar-beneficio="{{ route('colaborador.beneficio.encerrar', $vinculo->id) }}">Encerrar</button>
                            @endif
                        @endpermission
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@permission('colaborador-beneficio-edit')
    @if ($colaborador->status !== \App\Enums\StatusColaborador::Desligado && $beneficiosDisponiveis->isNotEmpty())
        <form id="form-beneficio" class="row g-2 align-items-end mb-3" action="{{ route('colaborador.beneficio.store', $colaborador->id) }}">
            <div class="col-sm-4">
                <label for="ben-id" class="small text-semibold">Benefício</label>
                <select id="ben-id" name="beneficio_id" class="form-select form-select-sm" required>
                    <option value="">Selecione</option>
                    @foreach ($beneficiosDisponiveis as $beneficio)
                        <option value="{{ $beneficio->id }}">{{ $beneficio->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3">
                <label for="ben-inicio" class="small text-semibold">A partir de</label>
                <input id="ben-inicio" type="date" name="inicio" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-sm-3">
                <label for="ben-valor" class="small text-semibold">Valor personalizado (R$)</label>
                <input id="ben-valor" type="number" step="0.01" min="0.01" name="valor_personalizado" class="form-control form-control-sm" placeholder="tabela vigente">
            </div>
            <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Vincular</button></div>
        </form>
    @endif
@endpermission

@push('scripts')
<script>
    (() => {
        const recarregar = () => requestIntoModal(@json($recarregarUrl));
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const form = document.getElementById('form-beneficio');
        if (form) form.addEventListener('submit', (ev) => {
            ev.preventDefault();
            axios.post(form.action, new FormData(form)).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
        });
        document.querySelectorAll('[data-encerrar-beneficio]').forEach((b) => b.addEventListener('click', () => {
            if (!confirm('Encerrar este benefício a partir de hoje?')) return;
            axios.post(b.dataset.encerrarBeneficio, {}).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
        }));
    })();
</script>
@endpush

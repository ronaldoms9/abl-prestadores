{{-- Obras e rateio do colaborador (E4, fatia 10). Espera $colaborador (com obras.obra, vigentes e futuras) e $obrasDisponiveis. --}}
<h6 class="text-semibold mt-3">Obras e rateio</h6>
@if ($colaborador->obras->isEmpty())
    <p class="text-muted">{{ $colaborador->obra ? 'Atua só na obra '.$colaborador->obra->descricao.' (100%).' : 'Sem obra: o que ele recebe entra como "Sem obra" nos relatórios.' }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Obra</th><th class="text-end">Percentual</th><th>De</th><th>Até</th></tr></thead>
            <tbody>
            @foreach ($colaborador->obras as $o)
                <tr>
                    <td>{{ $o->obra->descricao }}</td>
                    <td class="text-end">{{ number_format((float) $o->percentual, 2, ',', '.') }}%</td>
                    <td>{{ $o->inicio->format('d/m/Y') }}{{ $o->inicio->gt(now()) ? ' (futuro)' : '' }}</td>
                    <td>{{ $o->fim ? $o->fim->copy()->subDay()->format('d/m/Y') : 'atual' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@permission('colaborador-edit')
    @if ($colaborador->status !== \App\Enums\StatusColaborador::Desligado && $obrasDisponiveis->isNotEmpty())
        <form id="form-obras" class="mb-3" action="{{ route('colaborador.obras.store', $colaborador->id) }}">
            <p class="small text-muted mb-2">O que o colaborador recebe é repartido entre as obras, pelo percentual, dia a dia. Os percentuais somam 100%. Vale a partir da data, e o que já foi aprovado não muda.</p>
            <div id="linhas-obras" class="d-flex flex-column gap-2">
                @php $linhas = $colaborador->obras->filter(fn ($o) => $o->fim === null)->map(fn ($o) => ['obra_id' => $o->obra_id, 'percentual' => number_format((float) $o->percentual, 2, '.', '')])->values()->all() ?: [['obra_id' => $colaborador->obra_id, 'percentual' => '100.00']]; @endphp
                @foreach ($linhas as $i => $l)
                    <div class="row g-2 align-items-end linha-obra">
                        <div class="col-sm-6">
                            <select name="obras[{{ $i }}][obra_id]" class="form-select form-select-sm" aria-label="Obra" required>
                                <option value="">Obra</option>
                                @foreach ($obrasDisponiveis as $obra)
                                    <option value="{{ $obra->id }}" @selected($l['obra_id'] === $obra->id)>{{ $obra->descricao }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <input type="number" step="0.01" min="0.01" max="100" name="obras[{{ $i }}][percentual]" class="form-control form-control-sm" value="{{ $l['percentual'] }}" aria-label="Percentual" required>
                        </div>
                        <div class="col-sm-3"><button type="button" class="btn btn-link btn-sm text-danger p-0" data-remover-obra>Remover</button></div>
                    </div>
                @endforeach
            </div>
            <div class="row g-2 align-items-end mt-1">
                <div class="col-sm-4">
                    <label for="obras-inicio" class="small text-semibold">A partir de</label>
                    <input id="obras-inicio" type="date" name="inicio" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-sm-4"><button type="button" class="btn btn-outline-secondary btn-sm w-100" id="add-obra">Adicionar obra</button></div>
                <div class="col-sm-4"><button type="submit" class="btn btn-primary btn-sm w-100">Salvar rateio</button></div>
            </div>
            <div class="small mt-1">Soma: <strong id="soma-obras">100,00</strong>%</div>
        </form>
    @endif
@endpermission

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('form-obras');
        if (!form) return;
        const recarregar = () => requestIntoModal(@json($recarregarUrl));
        const linhas = document.getElementById('linhas-obras');
        const soma = () => {
            const total = [...linhas.querySelectorAll('input[type=number]')].reduce((t, i) => t + (parseFloat(i.value) || 0), 0);
            document.getElementById('soma-obras').textContent = total.toFixed(2).replace('.', ',');
        };
        const renumerar = () => linhas.querySelectorAll('.linha-obra').forEach((l, i) => {
            l.querySelector('select').name = 'obras[' + i + '][obra_id]';
            l.querySelector('input[type=number]').name = 'obras[' + i + '][percentual]';
        });
        document.getElementById('add-obra').addEventListener('click', () => {
            const nova = linhas.querySelector('.linha-obra').cloneNode(true);
            nova.querySelector('select').value = '';
            nova.querySelector('input[type=number]').value = '';
            linhas.appendChild(nova);
            renumerar();
        });
        linhas.addEventListener('click', (e) => {
            if (!e.target.matches('[data-remover-obra]')) return;
            if (linhas.querySelectorAll('.linha-obra').length > 1) e.target.closest('.linha-obra').remove();
            renumerar();
            soma();
        });
        linhas.addEventListener('input', soma);
        soma();
        form.addEventListener('submit', (ev) => {
            ev.preventDefault();
            axios.post(form.action, new FormData(form))
                .then((r) => { showNotify('Sucesso', r.data.message); recarregar(); })
                .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
        });
    })();
</script>
@endpush

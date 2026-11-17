@extends('layouts.app', ['title' => 'Reajuste de benefícios'])

@section('content')
<div class="container-fluid">
    <div class="card shadow mb-3">
        <div class="card-header border-0"><h5 class="mb-0">Reajuste de benefícios em lote</h5></div>
        <div class="card-body">
            <p class="text-muted small">Reajusta a tabela de valores. Valores personalizados de colaboradores não mudam.</p>
            <form id="form-lote" onsubmit="return false">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="beneficio_ids" class="small text-semibold">Benefícios (Ctrl ou Cmd para vários)</label>
                        <select id="beneficio_ids" name="beneficio_ids[]" class="form-select" multiple size="6">
                            @foreach ($beneficios as $b)
                                <option value="{{ $b->id }}">{{ $b->nome }} ({{ $b->valores->first() ? 'R$ '.number_format($b->valores->first()->valor, 2, ',', '.') : 'sem valor' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="forma" class="small text-semibold">Forma</label>
                                <select id="forma" name="forma" class="form-select">
                                    <option value="percentual">Percentual (%) sobre o atual</option>
                                    <option value="valor">Valor (R$) somado ao atual</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="valor" class="small text-semibold">Valor do reajuste</label>
                                <input id="valor" type="number" step="0.01" min="0.01" name="valor" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label for="vigencia" class="small text-semibold">Vale a partir de</label>
                                <input id="vigencia" type="date" name="vigencia" class="form-control" value="{{ now()->addMonthNoOverflow()->startOfMonth()->toDateString() }}" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" id="btn-previa" class="btn btn-primary w-100">Ver prévia</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow" id="card-previa" hidden>
        <div class="card-header border-0"><h5 class="mb-0">Prévia</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Benefício</th><th>Cálculo</th><th class="text-end">Atual</th><th class="text-end">Novo</th><th class="text-end">Diferença</th><th>Observação</th></tr></thead>
                    <tbody id="previa-linhas"></tbody>
                </table>
            </div>
            <div id="previa-ignorados" class="text-muted small mb-2"></div>
            <button type="button" id="btn-aplicar" class="btn btn-success" disabled>Confirmar e aplicar</button>
            <span class="text-muted small ms-2">Grava tudo de uma vez; se algo falhar, nada é gravado.</span>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    (() => {
        const form = document.getElementById('form-lote');
        const card = document.getElementById('card-previa');
        const linhas = document.getElementById('previa-linhas');
        const btnAplicar = document.getElementById('btn-aplicar');
        let hash = null;

        form.addEventListener('input', () => { hash = null; btnAplicar.disabled = true; card.hidden = true; });

        const dados = () => { const d = new FormData(form); if (hash) d.append('hash', hash); return d; };
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const td = (texto, classe) => { const c = document.createElement('td'); if (classe) c.className = classe; c.textContent = texto ?? ''; return c; };

        document.getElementById('btn-previa').addEventListener('click', () => {
            axios.post(@json(route('reajuste.beneficios.previa')), dados()).then((r) => {
                const p = r.data.data;
                hash = p.hash;
                linhas.replaceChildren(...p.linhas.map((l) => {
                    const tr = document.createElement('tr');
                    if (l.erro) tr.className = 'table-danger';
                    tr.append(td(l.nome), td(l.tipo), td(l.atual, 'text-end'), td(l.novo, 'text-end'), td(l.diferenca, 'text-end'), td(l.erro ? 'Erro: ' + l.erro : ''));
                    return tr;
                }));
                document.getElementById('previa-ignorados').textContent = p.ignorados.length
                    ? 'Fora do reajuste: ' + p.ignorados.map((i) => i.nome + ' (' + i.motivo + ')').join(', ') + '.' : '';
                btnAplicar.disabled = p.erros > 0;
                if (p.erros > 0) showNotify('Atenção', 'Há linhas com erro. Corrija a escolha ou a data para confirmar.', 'error');
                card.hidden = false;
            }).catch(falha);
        });

        btnAplicar.addEventListener('click', () => {
            if (!confirm('Aplicar o reajuste a todos os benefícios da prévia?')) return;
            axios.post(@json(route('reajuste.beneficios.aplicar')), dados()).then((r) => {
                showNotify('Sucesso', r.data.message);
                setTimeout(() => location.reload(), 900);
            }).catch(falha);
        });
    })();
</script>
@endpush

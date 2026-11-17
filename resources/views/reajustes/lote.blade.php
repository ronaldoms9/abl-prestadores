@extends('layouts.app', ['title' => 'Reajuste em lote'])

@section('content')
<div class="container-fluid">
    <div class="card shadow mb-3">
        <div class="card-header border-0"><h5 class="mb-0">Reajuste em lote</h5></div>
        <div class="card-body">
            <form id="form-lote" onsubmit="return false">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo" class="small text-semibold">Quem recebe</label>
                        <select id="tipo" name="tipo" class="form-select">
                            <option value="cargo">Todos de um cargo</option>
                            <option value="lista">Colaboradores escolhidos</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="bloco-cargo">
                        <label for="cargo_id" class="small text-semibold">Cargo</label>
                        <select id="cargo_id" name="cargo_id" class="form-select">
                            <option value="">Selecione</option>
                            @foreach ($cargos as $cargo)<option value="{{ $cargo->id }}">{{ $cargo->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6" id="bloco-lista" hidden>
                        <label for="colaborador_ids" class="small text-semibold">Colaboradores (Ctrl ou Cmd para vários)</label>
                        <select id="colaborador_ids" name="colaborador_ids[]" class="form-select" multiple size="6">
                            @foreach ($colaboradores as $c)<option value="{{ $c->id }}">{{ $c->pessoaFisica->nome }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-3">
                        <label for="forma" class="small text-semibold">Forma</label>
                        <select id="forma" name="forma" class="form-select">
                            <option value="percentual">Percentual (%) sobre o atual</option>
                            <option value="valor">Valor (R$) somado ao atual</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="valor" class="small text-semibold">Valor do reajuste</label>
                        <input id="valor" type="number" step="0.01" min="0.01" name="valor" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label for="vigencia" class="small text-semibold">Vale a partir de</label>
                        <input id="vigencia" type="date" name="vigencia" class="form-control" value="{{ now()->addMonthNoOverflow()->startOfMonth()->toDateString() }}" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="btn-previa" class="btn btn-primary w-100">Ver prévia</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mb-3" id="card-previa" hidden>
        <div class="card-header border-0"><h5 class="mb-0">Prévia</h5></div>
        <div class="card-body">
            <div id="previa-resumo" class="mb-2"></div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Colaborador</th><th>Cargo</th><th class="text-end">Atual</th><th class="text-end">Novo</th><th class="text-end">Diferença</th><th>Observação</th></tr></thead>
                    <tbody id="previa-linhas"></tbody>
                </table>
            </div>
            <div id="previa-ignorados" class="text-muted small mb-2"></div>
            <button type="button" id="btn-aplicar" class="btn btn-success" disabled>Confirmar e aplicar</button>
            <span class="text-muted small ms-2">Grava tudo de uma vez; se algo falhar, nada é gravado.</span>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-header border-0"><h6 class="mb-0">Últimos reajustes</h6></div>
        <div class="card-body">
            @if ($ultimos->isEmpty())
                <p class="text-muted mb-0">Nenhum reajuste feito ainda.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Quando</th><th>Tipo</th><th>Reajuste</th><th>Vale desde</th><th>Aprovado por</th></tr></thead>
                        <tbody>
                        @foreach ($ultimos as $r)
                            <tr>
                                <td>{{ $r->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $r->tipo->rotulo() }}</td>
                                <td>{{ $r->forma === \App\Enums\FormaReajuste::Percentual ? number_format($r->valor, 2, ',', '.').' %' : 'R$ '.number_format($r->valor, 2, ',', '.') }}</td>
                                <td>{{ $r->vigencia_inicio->format('d/m/Y') }}</td>
                                <td>{{ $r->aprovador?->name ?? '—' }}</td>
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
    (() => {
        const form = document.getElementById('form-lote');
        const card = document.getElementById('card-previa');
        const linhas = document.getElementById('previa-linhas');
        const btnAplicar = document.getElementById('btn-aplicar');
        let hash = null;

        const alternar = () => {
            const lista = document.getElementById('tipo').value === 'lista';
            document.getElementById('bloco-lista').hidden = !lista;
            document.getElementById('bloco-cargo').hidden = lista;
        };
        document.getElementById('tipo').addEventListener('change', alternar);
        form.addEventListener('input', () => { hash = null; btnAplicar.disabled = true; card.hidden = true; });

        const dados = () => { const d = new FormData(form); if (hash) d.append('hash', hash); return d; };
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
        const td = (texto, classe) => { const c = document.createElement('td'); if (classe) c.className = classe; c.textContent = texto ?? ''; return c; };

        document.getElementById('btn-previa').addEventListener('click', () => {
            axios.post(@json(route('reajuste.lote.previa')), dados()).then((r) => {
                const p = r.data.data;
                hash = p.hash;
                linhas.replaceChildren(...p.linhas.map((l) => {
                    const tr = document.createElement('tr');
                    if (l.erro) tr.className = 'table-danger';
                    tr.append(td(l.nome), td(l.cargo || '—'), td(l.atual, 'text-end'), td(l.novo, 'text-end'), td(l.diferenca, 'text-end'),
                        td(l.erro ? 'Erro: ' + l.erro : (l.aviso_faixa ? 'Atenção: ' + l.aviso_faixa : '')));
                    return tr;
                }));
                document.getElementById('previa-resumo').textContent = p.linhas.length + ' colaborador(es). Folha de ' + p.total_atual + ' passa a ' + p.total_novo + ' (' + p.total_diferenca + ').';
                document.getElementById('previa-ignorados').textContent = p.ignorados.length
                    ? 'Fora do reajuste: ' + p.ignorados.map((i) => i.nome + ' (' + i.motivo + ')').join(', ') + '.' : '';
                btnAplicar.disabled = p.erros > 0;
                if (p.erros > 0) showNotify('Atenção', 'Há linhas com erro. Corrija o critério ou a data para confirmar.', 'error');
                card.hidden = false;
            }).catch(falha);
        });

        btnAplicar.addEventListener('click', () => {
            if (!confirm('Aplicar o reajuste a todos os colaboradores da prévia?')) return;
            axios.post(@json(route('reajuste.lote.aplicar')), dados()).then((r) => {
                showNotify('Sucesso', r.data.message);
                setTimeout(() => location.reload(), 900);
            }).catch(falha);
        });
        alternar();
    })();
</script>
@endpush

@extends('layouts.modal', [
    'title' => 'Desligar '.$colaborador->pessoaFisica->nome,
    'formroute' => route('desligamento.store', $colaborador->id),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-danger pull-right" id="btn-desligar">Desligar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-sm-4">
            <label for="deslig-data" class="small text-semibold">Último dia de trabalho</label>
            <input id="deslig-data" type="date" name="data" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"
                   @if ($colaborador->data_admissao) min="{{ $colaborador->data_admissao->toDateString() }}" @endif required>
        </div>
        <div class="col-sm-8">
            <label for="deslig-motivo" class="small text-semibold">Motivo</label>
            <input id="deslig-motivo" type="text" name="motivo" class="form-control" maxlength="255" required>
        </div>
        <div class="col-12">
            <label for="deslig-obs" class="small text-semibold">Observação (opcional)</label>
            <textarea id="deslig-obs" name="observacao" class="form-control" rows="2" maxlength="1000"></textarea>
        </div>
    </div>

    @if ($temContrato)
        <div class="form-check form-switch mt-3">
            <input type="hidden" name="gerar_distrato" value="0">
            <input id="deslig-distrato" type="checkbox" role="switch" class="form-check-input" name="gerar_distrato" value="1" checked>
            <label for="deslig-distrato" class="form-check-label">Gerar o distrato do contrato</label>
        </div>
        @if ($modelosDistrato->count() > 1)
            <div class="form-group mt-2">
                <label for="deslig-modelo" class="small text-semibold">Modelo do distrato</label>
                <select id="deslig-modelo" name="modelo_distrato_id" class="form-select form-select-sm">
                    @foreach ($modelosDistrato as $modelo)<option value="{{ $modelo->id }}">{{ $modelo->nome }}</option>@endforeach
                </select>
            </div>
        @endif
    @else
        <p class="text-muted small mt-3 mb-0">O colaborador não tem contrato gerado ou assinado: não há distrato a gerar.</p>
    @endif

    <h6 class="text-semibold mt-4">Valores finais do mês</h6>
    <div id="previa-carregando" class="text-muted small">Calculando…</div>
    <div id="previa" hidden>
        <p class="small text-muted mb-1" id="previa-periodo"></p>
        <table class="table table-sm mb-1">
            <tbody id="previa-linhas"></tbody>
            <tfoot><tr><th>Total</th><th class="text-end" id="previa-total"></th></tr></tfoot>
        </table>
        <div id="previa-avisos" class="small text-muted"></div>
    </div>
    <p class="text-muted small mt-3 mb-0">Ao desligar, o contrato é encerrado, a remuneração, os benefícios e as equipes fecham no dia seguinte e o colaborador deixa de receber novos lançamentos.</p>

    @push('scripts')
    <script>
        (() => {
            const data = document.getElementById('deslig-data');
            const linhas = document.getElementById('previa-linhas');
            const linha = (nome, valor, negrito) => {
                const tr = document.createElement('tr');
                const a = document.createElement('td'); a.textContent = nome;
                const b = document.createElement('td'); b.className = 'text-end'; b.textContent = valor;
                if (negrito) { a.className = b.className += ' fw-semibold'; }
                tr.append(a, b);
                return tr;
            };
            const carregar = () => {
                if (!data.value) return;
                axios.get(@json(route('desligamento.previa', $colaborador->id)), { params: { data: data.value } }).then((r) => {
                    const p = r.data.data;
                    linhas.replaceChildren(
                        linha('Remuneração proporcional', p.remuneracao),
                        ...p.beneficios.map((b) => linha(b.nome + (b.personalizado ? ' (personalizado)' : ''), b.valor)),
                    );
                    document.getElementById('previa-periodo').textContent = p.periodo ? 'Período: ' + p.periodo : '';
                    document.getElementById('previa-total').textContent = p.total;
                    document.getElementById('previa-avisos').replaceChildren(...p.avisos.map((a) => { const d = document.createElement('div'); d.textContent = a; return d; }));
                    document.getElementById('previa').hidden = false;
                    document.getElementById('previa-carregando').hidden = true;
                }).catch(() => { document.getElementById('previa-carregando').textContent = 'Não foi possível calcular a prévia agora.'; });
            };
            data.addEventListener('change', carregar);
            carregar();
        })();
    </script>
    @endpush
@endsection

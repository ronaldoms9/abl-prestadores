@extends('layouts.modal', [
    'title' => $colaborador->pessoaFisica->nome,
    'size' => 'modal-wide',
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    <dl class="row mb-3">
        <dt class="col-sm-3">CPF</dt>
        <dd class="col-sm-9">{{ $colaborador->pessoaFisica->cpf_formatado }}</dd>

        <dt class="col-sm-3">Situação</dt>
        <dd class="col-sm-9">{{ $colaborador->status->rotulo() }}</dd>

        <dt class="col-sm-3">Pessoa jurídica</dt>
        <dd class="col-sm-9">{{ $colaborador->pessoaJuridica->razao_social }} ({{ $colaborador->pessoaJuridica->cnpj_formatado }})</dd>

        <dt class="col-sm-3">Cargo</dt>
        <dd class="col-sm-9">{{ $colaborador->cargo?->nome ?? '—' }}</dd>

        <dt class="col-sm-3">Empresa</dt>
        <dd class="col-sm-9">{{ $colaborador->empresa?->razao_social ?? '—' }}</dd>

        <dt class="col-sm-3">Obra</dt>
        <dd class="col-sm-9">{{ $colaborador->obra?->descricao ?? '—' }}</dd>

        <dt class="col-sm-3">Admissão</dt>
        <dd class="col-sm-9">{{ $colaborador->data_admissao?->format('d/m/Y') ?? '—' }}</dd>
    </dl>

    @permission('remuneracao-list')
        @php
            $atual = $colaborador->remuneracoes->firstWhere('vigencia_fim', null);
            $fmt = fn ($v) => 'R$ '.number_format($v, 2, ',', '.');
            $aviso = $atual ? $colaborador->cargo?->avisoDeFaixa(\App\Support\Dinheiro::centavos($atual->valor_mensal)) : null;
        @endphp
        <h6 class="text-semibold">Remuneração</h6>
        @if ($colaborador->remuneracoes->isEmpty())
            <p class="text-muted">Sem remuneração definida.</p>
        @else
            <p class="mb-1"><strong>{{ $fmt($atual->valor_mensal) }}</strong> por mês, desde {{ $atual->vigencia_inicio->format('d/m/Y') }}.
                @if ($aviso)<span class="text-warning">Atenção: {{ $aviso }}.</span>@endif
            </p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>De</th><th>Até</th><th class="text-end">Valor mensal</th></tr></thead>
                    <tbody>
                    @foreach ($colaborador->remuneracoes as $linha)
                        <tr>
                            <td>{{ $linha->vigencia_inicio->format('d/m/Y') }}</td>
                            <td>{{ $linha->vigencia_fim ? $linha->vigencia_fim->copy()->subDay()->format('d/m/Y') : 'atual' }}</td>
                            <td class="text-end">{{ $fmt($linha->valor_mensal) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @permission('remuneracao-reajustar')
            @if ($colaborador->status !== \App\Enums\StatusColaborador::Desligado)
                <form id="form-remuneracao" class="row g-2 align-items-end mb-3"
                      action="{{ $atual ? route('remuneracao.reajustar', $colaborador->id) : route('remuneracao.definir', $colaborador->id) }}">
                    @if ($atual)
                        <div class="col-sm-3">
                            <label for="rem-forma" class="small text-semibold">Reajuste</label>
                            <select id="rem-forma" name="forma" class="form-select form-select-sm">
                                <option value="percentual">Percentual (%)</option>
                                <option value="valor">Valor (R$) somado</option>
                            </select>
                        </div>
                    @endif
                    <div class="col-sm-3">
                        <label for="rem-valor" class="small text-semibold">{{ $atual ? 'Valor do reajuste' : 'Remuneração mensal (R$)' }}</label>
                        <input id="rem-valor" type="number" step="0.01" min="0.01" name="valor" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-sm-4">
                        <label for="rem-vigencia" class="small text-semibold">Vale a partir de</label>
                        <input id="rem-vigencia" type="date" name="vigencia" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">{{ $atual ? 'Reajustar' : 'Definir' }}</button></div>
                </form>
            @endif
        @endpermission
    @endpermission

    <h6 class="text-semibold">Equipes</h6>
    @if ($colaborador->vinculosEquipe->isEmpty())
        <p class="text-muted">Não está em nenhuma equipe.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Equipe</th><th>Desde</th><th>Até</th><th></th></tr></thead>
                <tbody>
                @foreach ($colaborador->vinculosEquipe as $vinculo)
                    <tr>
                        <td>{{ $vinculo->equipe->nome }}</td>
                        <td>{{ $vinculo->inicio->format('d/m/Y') }}</td>
                        <td>{{ $vinculo->fim?->format('d/m/Y') ?? 'atual' }}</td>
                        <td class="text-end">
                            @permission('colaborador-edit')
                                @if (! $vinculo->fim)
                                    <button type="button" class="btn btn-link btn-sm text-danger p-0"
                                            data-encerrar="{{ route('colaborador.equipe.encerrar', $vinculo->id) }}">Retirar</button>
                                @endif
                            @endpermission
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @permission('colaborador-edit')
        @if ($equipesLivres->isNotEmpty())
            <form id="form-equipe" class="row g-2 align-items-end mb-3" action="{{ route('colaborador.equipe.store', $colaborador->id) }}">
                <div class="col-sm-6">
                    <label for="equipe-id" class="small text-semibold">Incluir em equipe</label>
                    <select id="equipe-id" name="equipe_id" class="form-select form-select-sm" required>
                        <option value="">Selecione</option>
                        @foreach ($equipesLivres as $equipe)
                            <option value="{{ $equipe->id }}">{{ $equipe->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4">
                    <label for="equipe-inicio" class="small text-semibold">A partir de</label>
                    <input id="equipe-inicio" type="date" name="inicio" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Incluir</button></div>
            </form>
        @endif
    @endpermission

    @permission('qualificacao-list')
        @include('cadastros._qualificacoes', ['recarregarUrl' => route('colaborador.show', $colaborador->id)])
    @endpermission

    @permission('documento-list')
        @include('cadastros._documentos', [
            'donoApelido' => 'colaborador',
            'donoId' => $colaborador->id,
            'documentos' => $colaborador->documentos,
            'recarregarUrl' => route('colaborador.show', $colaborador->id),
        ])
    @endpermission

    <h6 class="text-semibold mt-3">Histórico</h6>
    @php $pessoa = $colaborador; @endphp
    @include('cadastros._historico')

    @push('scripts')
    <script>
        (() => {
            const recarregar = () => requestIntoModal(@json(route('colaborador.show', $colaborador->id)));
            const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
            const formRem = document.getElementById('form-remuneracao');
            if (formRem) formRem.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(formRem.action, new FormData(formRem)).then((r) => { showNotify('Sucesso', r.data.message); recarregar(); }).catch(falha);
            });
            const form = document.getElementById('form-equipe');
            if (form) form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(form.action, new FormData(form)).then(() => { showNotify('Sucesso', 'Colaborador incluído na equipe.'); recarregar(); }).catch(falha);
            });
            document.querySelectorAll('[data-encerrar]').forEach((b) => b.addEventListener('click', () => {
                if (!confirm('Retirar o colaborador desta equipe a partir de hoje?')) return;
                axios.post(b.dataset.encerrar, {}).then(() => { showNotify('Sucesso', 'Colaborador retirado.'); recarregar(); }).catch(falha);
            }));
        })();
    </script>
    @endpush
@endsection

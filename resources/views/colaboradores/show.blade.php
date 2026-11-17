@extends('layouts.modal', [
    'title' => $colaborador->pessoaFisica->nome,
    'size' => 'modal-lg',
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

    <h6 class="text-semibold mt-3">Histórico</h6>
    @php $pessoa = $colaborador; @endphp
    @include('cadastros._historico')

    @push('scripts')
    <script>
        (() => {
            const recarregar = () => requestIntoModal(@json(route('colaborador.show', $colaborador->id)));
            const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
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

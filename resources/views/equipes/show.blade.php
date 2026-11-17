@extends('layouts.modal', [
    'title' => $equipe->nome,
    'size' => 'modal-wide',
])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    @if ($equipe->descricao)
        <p>{{ $equipe->descricao }}</p>
    @endif

    <h6 class="text-semibold">Gestores</h6>
    @if ($equipe->gestores->isEmpty())
        <p class="text-muted">Nenhum gestor ainda.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Gestor</th><th>Desde</th><th>Até</th><th></th></tr></thead>
                <tbody>
                @foreach ($equipe->gestores as $gestor)
                    <tr>
                        <td>{{ $gestor->usuario->name }}</td>
                        <td>{{ $gestor->inicio->format('d/m/Y') }}</td>
                        <td>{{ $gestor->fim?->format('d/m/Y') ?? 'atual' }}</td>
                        <td class="text-end">
                            @permission('equipe-edit')
                                @if (! $gestor->fim)
                                    <button type="button" class="btn btn-link btn-sm text-danger p-0"
                                            data-encerrar="{{ route('equipe.gestor.encerrar', $gestor->id) }}">Retirar</button>
                                @endif
                            @endpermission
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @permission('equipe-edit')
        <form id="form-gestor" class="row g-2 align-items-end mb-3" action="{{ route('equipe.gestor.store', $equipe->id) }}">
            <div class="col-sm-6">
                <label for="gestor-user" class="small text-semibold">Adicionar gestor</label>
                <select id="gestor-user" name="user_id" class="form-select form-select-sm" required>
                    <option value="">Selecione</option>
                    @foreach ($candidatos as $usuario)
                        <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-4">
                <label for="gestor-inicio" class="small text-semibold">A partir de</label>
                <input id="gestor-inicio" type="date" name="inicio" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Adicionar</button></div>
            @if ($candidatos->isEmpty())
                <div class="col-12 text-muted small">Nenhum usuário ativo tem a permissão "Gerir equipes". Dê a permissão a um perfil primeiro.</div>
            @endif
        </form>
    @endpermission

    <h6 class="text-semibold">Colaboradores atuais</h6>
    @if ($equipe->vinculosColaborador->isEmpty())
        <p class="text-muted mb-0">Nenhum colaborador vinculado.</p>
    @else
        <ul class="mb-0">
            @foreach ($equipe->vinculosColaborador as $vinculo)
                <li>{{ $vinculo->colaborador->pessoaFisica->nome }} · desde {{ $vinculo->inicio->format('d/m/Y') }}</li>
            @endforeach
        </ul>
    @endif

    @push('scripts')
    <script>
        (() => {
            const recarregar = () => requestIntoModal(@json(route('equipe.show', $equipe->id)));
            const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');
            const form = document.getElementById('form-gestor');
            if (form) form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(form.action, new FormData(form)).then(() => { showNotify('Sucesso', 'Gestor adicionado.'); recarregar(); }).catch(falha);
            });
            document.querySelectorAll('[data-encerrar]').forEach((b) => b.addEventListener('click', () => {
                if (!confirm('Retirar este gestor da equipe a partir de hoje?')) return;
                axios.post(b.dataset.encerrar, {}).then(() => { showNotify('Sucesso', 'Gestor retirado.'); recarregar(); }).catch(falha);
            }));
        })();
    </script>
    @endpush
@endsection

@extends('layouts.app', ['title' => 'Níveis de aprovação de férias'])

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow">
        <div class="card-header border-0 d-flex align-items-center">
            <h5 class="mb-0 me-auto">Níveis de aprovação de férias</h5>
            <a href="javascript:requestIntoModal('{{ route('ferias-nivel.form') }}')" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo nível</a>
        </div>
        <div class="card-body">
            <p class="text-muted small">A solicitação passa pelos níveis ativos, em ordem. Em cada um, qualquer aprovador decide. Nível sem aprovador elegível (por exemplo, colaborador sem equipe) é pulado e a escalada fica registrada.
                Depois do último nível, ou sem nenhum, a solicitação vai a quem tem "Aprovar qualquer férias". O solicitante e o próprio colaborador nunca aprovam as suas férias.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Ordem</th><th>Aprovador</th><th>Permissão</th><th>Situação</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($niveis as $n)
                        <tr>
                            <td>{{ $n->ordem }}</td>
                            <td>{{ $n->tipo_aprovador->rotulo() }}</td>
                            <td>{{ $n->permissao?->display_name ?? '—' }}</td>
                            <td>{!! $n->ativo ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' !!}</td>
                            <td class="text-end text-nowrap">
                                <a href="javascript:requestIntoModal('{{ route('ferias-nivel.form', $n->id) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                                <a href="javascript:deleteDefault('{{ route('ferias-nivel.delete', $n->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

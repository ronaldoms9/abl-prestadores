@extends('layouts.app', ['title' => 'Fluxos de aprovação de férias'])

@section('content')
<div class="container-fluid" data-recarregar>
    <div class="card shadow mb-3">
        <div class="card-header border-0 d-flex align-items-center">
            <h5 class="mb-0 me-auto">Fluxos de aprovação de férias</h5>
            <a href="javascript:requestIntoModal('{{ route('ferias-fluxo.form') }}')" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Novo fluxo</a>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-1">Cada solicitação segue um fluxo, escolhido pelo colaborador: vale o mais específico, nesta ordem — o do próprio colaborador, o da função dentro da equipe, o da equipe, o da função e, por último, o padrão.
                Em cada fluxo, a solicitação passa pelos níveis ativos, em ordem, e em cada nível qualquer aprovador decide.</p>
            <p class="text-muted small mb-0">Nível sem aprovador elegível (por exemplo, colaborador sem equipe) é pulado e a escalada fica registrada. Depois do último nível, ou sem nenhum, a solicitação vai a quem tem "Aprovar qualquer férias".
                O solicitante e o próprio colaborador nunca aprovam as suas férias. Mudar um fluxo vale para as solicitações novas; as pendentes seguem o fluxo em que foram feitas.</p>
        </div>
    </div>

    @foreach ($fluxos as $f)
        <div class="card shadow mb-3">
            <div class="card-header border-0 d-flex align-items-center flex-wrap gap-2">
                <div class="me-auto">
                    <h6 class="mb-0">{{ $f->nome }} {!! $f->ativo ? '' : '<span class="badge text-bg-secondary">Inativo</span>' !!}</h6>
                    <div class="text-muted small">
                        {{ $f->escopo->rotulo() }}@if ($f->escopo === \App\Enums\EscopoFluxoFerias::Colaborador): {{ $f->colaborador?->pessoaFisica?->nome }}
                        @elseif ($f->escopo === \App\Enums\EscopoFluxoFerias::EquipeCargo): {{ $f->cargo?->nome }} na equipe {{ $f->equipe?->nome }}
                        @elseif ($f->escopo === \App\Enums\EscopoFluxoFerias::Equipe): {{ $f->equipe?->nome }}
                        @elseif ($f->escopo === \App\Enums\EscopoFluxoFerias::Cargo): {{ $f->cargo?->nome }}
                        @endif
                    </div>
                </div>
                <a href="javascript:requestIntoModal('{{ route('ferias-nivel.form') }}?fluxo={{ $f->id }}')" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg"></i> Nível</a>
                <a href="javascript:requestIntoModal('{{ route('ferias-fluxo.form', $f->id) }}')" class="text-primary acao-icone" title="Editar fluxo" aria-label="Editar fluxo"><i class="bi bi-pencil-square"></i></a>
                @unless ($f->ePadrao())
                    <a href="javascript:deleteDefault('{{ route('ferias-fluxo.delete', $f->id) }}')" class="text-danger acao-icone" title="Excluir fluxo" aria-label="Excluir fluxo"><i class="bi bi-trash"></i></a>
                @endunless
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Ordem</th><th>Aprovador</th><th>Quem</th><th>Situação</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($f->niveis as $n)
                            <tr>
                                <td>{{ $n->ordem }}</td>
                                <td>{{ $n->tipo_aprovador->rotulo() }}</td>
                                <td>{{ $n->tipo_aprovador === \App\Enums\TipoAprovadorFerias::Usuario ? $n->usuario?->name : ($n->permissao?->display_name ?? '—') }}</td>
                                <td>{!! $n->ativo ? '<span class="badge text-bg-success">Ativo</span>' : '<span class="badge text-bg-secondary">Inativo</span>' !!}</td>
                                <td class="text-end text-nowrap">
                                    <a href="javascript:requestIntoModal('{{ route('ferias-nivel.form', $n->id) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                                    <a href="javascript:deleteDefault('{{ route('ferias-nivel.delete', $n->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Sem níveis: este fluxo não é usado até ter ao menos um nível ativo.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

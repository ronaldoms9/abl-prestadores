@if ($log->status === \App\Enums\StatusIntegracao::Sucesso)
    <span class="badge text-bg-success">Sucesso</span>
@elseif ($log->resolvida())
    <span class="badge text-bg-secondary" title="Uma chamada posterior da mesma operação deu certo">Erro resolvido</span>
@else
    <span class="badge text-bg-danger">{{ $log->status->rotulo() }}</span>
@endif

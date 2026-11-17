<a href="javascript:requestIntoModal('{{ route('integracao.show', [$log->id]) }}')" class="text-primary acao-icone" title="Detalhes" aria-label="Detalhes"><i class="bi bi-eye"></i></a>
@permission('integracao-reenviar')
    @if (\App\Actions\Integracoes\ReprocessarFalha::motivoParaRecusar($log) === null)
        <a href="javascript:postAction('{{ route('integracao.reprocessar', [$log->id]) }}', 'Enviar esta importação de novo para a fila?')"
           class="text-warning ms-2 acao-icone" title="Reprocessar" aria-label="Reprocessar"><i class="bi bi-arrow-repeat"></i></a>
    @endif
@endpermission

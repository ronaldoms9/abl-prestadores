<a href="javascript:requestIntoModal('{{ route('integracao.show', [$log->id]) }}')" class="text-primary">Detalhes</a>
@permission('integracao-reenviar')
    @if (\App\Actions\Integracoes\ReprocessarFalha::motivoParaRecusar($log) === null)
        <a href="javascript:postAction('{{ route('integracao.reprocessar', [$log->id]) }}', 'Enviar esta importação de novo para a fila?')"
           class="text-warning ms-2">Reprocessar</a>
    @endif
@endpermission

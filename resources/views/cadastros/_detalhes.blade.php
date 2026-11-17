<a href="javascript:requestIntoModal('{{ $url }}')" class="text-primary acao-icone" title="Detalhes" aria-label="Detalhes"><i class="bi bi-eye"></i></a>
@if (! empty($editar))
    <a href="javascript:requestIntoModal('{{ $editar }}')" class="text-primary ms-2 acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
@endif

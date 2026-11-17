<a href="javascript:requestIntoModal('{{ route('colaborador.show', $colaborador->id) }}')" class="text-primary acao-icone" title="Detalhes" aria-label="Detalhes"><i class="bi bi-eye"></i></a>
@permission('colaborador-edit')
    <a href="javascript:requestIntoModal('{{ route('colaborador.form', $colaborador->id) }}')" class="text-primary ms-2 acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
@endpermission

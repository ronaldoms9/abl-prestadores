<a href="javascript:requestIntoModal('{{ route('equipe.show', $equipe->id) }}')" class="text-primary acao-icone" title="Detalhes" aria-label="Detalhes"><i class="bi bi-eye"></i></a>
@permission('equipe-edit')
    <a href="javascript:requestIntoModal('{{ route('equipe.form', $equipe->id) }}')" class="text-primary ms-2 acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
    <a href="javascript:deleteDefault('{{ route('equipe.delete', $equipe->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
@endpermission

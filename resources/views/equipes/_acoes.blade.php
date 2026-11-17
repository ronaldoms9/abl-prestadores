<a href="javascript:requestIntoModal('{{ route('equipe.show', $equipe->id) }}')" class="text-primary">Detalhes</a>
@permission('equipe-edit')
    <a href="javascript:requestIntoModal('{{ route('equipe.form', $equipe->id) }}')" class="text-primary ms-2">Editar</a>
    <a href="javascript:deleteDefault('{{ route('equipe.delete', $equipe->id) }}')" class="text-danger ms-2">Excluir</a>
@endpermission

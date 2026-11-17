@permission('user-edit')
<a href="javascript:requestIntoModal('{{ route('user.form',[$model->id]) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
@endpermission
@permission('user-delete')
<a href="javascript:deleteDefault('{{ route('user.delete',[$model->id]) }}')" class="text-danger acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
@endpermission

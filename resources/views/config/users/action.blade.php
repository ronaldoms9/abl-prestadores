@permission('user-edit')
<a href="javascript:requestIntoModal('{{ route('user.form',[$model->id]) }}')" class="text-primary">Editar </a>
@endpermission
@permission('user-delete')
<a href="javascript:deleteDefault('{{ route('user.delete',[$model->id]) }}')" class="text-danger">Excluir </a>
@endpermission

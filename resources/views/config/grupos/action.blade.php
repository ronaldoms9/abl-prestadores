@permission('grupo-edit')
<a href="javascript:requestIntoModal('{{ route('grupo.form',[$model->id]) }}')" class="text-primary">Editar </a>
@endpermission
@permission('grupo-delete')
<a href="javascript:deleteDefault('{{ route('grupo.delete',[$model->id]) }}')" class="text-danger">Excluir </a>
@endpermission

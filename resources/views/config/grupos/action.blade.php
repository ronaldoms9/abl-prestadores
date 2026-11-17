@permission('grupo-edit')
<a href="javascript:requestIntoModal('{{ route('grupo.form',[$model->id]) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
@endpermission
@permission('grupo-delete')
<a href="javascript:deleteDefault('{{ route('grupo.delete',[$model->id]) }}')" class="text-danger acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
@endpermission

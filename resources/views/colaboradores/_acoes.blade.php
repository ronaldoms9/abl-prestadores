<a href="javascript:requestIntoModal('{{ route('colaborador.show', $colaborador->id) }}')" class="text-primary">Detalhes</a>
@permission('colaborador-edit')
    <a href="javascript:requestIntoModal('{{ route('colaborador.form', $colaborador->id) }}')" class="text-primary ms-2">Editar</a>
@endpermission

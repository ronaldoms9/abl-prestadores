@permission('cargo-edit')
    <a href="javascript:requestIntoModal('{{ route('cargo.form', $cargo->id) }}')" class="text-primary">Editar</a>
    <a href="javascript:deleteDefault('{{ route('cargo.delete', $cargo->id) }}')" class="text-danger ms-2">Excluir</a>
@endpermission

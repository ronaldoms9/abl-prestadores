<a href="javascript:requestIntoModal('{{ route('beneficio.valores', $beneficio->id) }}')" class="text-primary acao-icone" title="Valores" aria-label="Valores"><i class="bi bi-cash-stack"></i></a>
@permission('beneficio-gerir')
    <a href="javascript:requestIntoModal('{{ route('beneficio.form', $beneficio->id) }}')" class="text-primary ms-2 acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
    <a href="javascript:deleteDefault('{{ route('beneficio.delete', $beneficio->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
@endpermission

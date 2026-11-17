<a href="javascript:requestIntoModal('{{ route('admissao-item.form', $item->id) }}')" class="text-primary acao-icone" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
@unless ($item->automatico())
    <a href="javascript:deleteDefault('{{ route('admissao-item.delete', $item->id) }}')" class="text-danger ms-2 acao-icone" title="Excluir" aria-label="Excluir"><i class="bi bi-trash"></i></a>
@endunless

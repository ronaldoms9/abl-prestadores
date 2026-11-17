@extends('layouts.modal', [
    'title' => $item->exists ? 'Editar item do checklist' : 'Novo item do checklist',
    'formroute' => route('admissao-item.store', $item->exists ? [$item->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    @if ($item->automatico())
        <p class="text-muted small">Item automático: o sistema marca sozinho, a partir do cadastro. Você pode mudar o nome, a ordem e se é obrigatório.</p>
    @endif
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome do item</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $item->nome }}" required maxlength="255">
    </div>
    <div class="form-group mt-2">
        <label for="ordem" class="text-semibold small">Ordem</label>
        <input id="ordem" type="number" min="0" max="9999" name="ordem" class="form-control" value="{{ $item->ordem }}" required>
    </div>
    <div class="form-check form-switch mt-3">
        <input type="hidden" name="obrigatorio" value="0">
        <input id="obrigatorio" type="checkbox" role="switch" class="form-check-input" name="obrigatorio" value="1" @checked($item->obrigatorio)>
        <label for="obrigatorio" class="form-check-label">Obrigatório para concluir a admissão</label>
    </div>
    @if ($item->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($item->ativo)>
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
    @endif
@endsection

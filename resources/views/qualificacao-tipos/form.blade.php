@extends('layouts.modal', [
    'title' => $tipo->exists ? 'Editar tipo de qualificação' : 'Novo tipo de qualificação',
    'formroute' => route('qualificacao-tipo.store', $tipo->exists ? [$tipo->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $tipo->nome }}" required>
    </div>
    <div class="row mt-2">
        <div class="col-sm-7 form-group">
            <label for="categoria" class="text-semibold small">Categoria</label>
            <select id="categoria" name="categoria" class="form-select">
                @foreach ($categorias as $c)
                    <option value="{{ $c->value }}" @selected($tipo->categoria === $c)>{{ $c->rotulo() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-5 form-group">
            <label for="validade_meses" class="text-semibold small">Validade (meses)</label>
            <input id="validade_meses" type="number" min="1" max="600" name="validade_meses" class="form-control" value="{{ $tipo->validade_meses }}">
        </div>
    </div>
    <p class="text-muted small mb-0">Contada a partir da conclusão. Em branco, a qualificação não vence.</p>
    @if ($tipo->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($tipo->ativo)>
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
    @endif
@endsection

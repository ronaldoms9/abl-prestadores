@extends('layouts.modal', [
    'title' => $cargo->exists ? 'Editar cargo' : 'Novo cargo',
    'formroute' => route('cargo.store', $cargo->exists ? [$cargo->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome do cargo</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $cargo->nome }}" required>
    </div>
    <div class="form-group mt-2">
        <label for="descricao" class="text-semibold small">Descrição</label>
        <textarea id="descricao" name="descricao" class="form-control" rows="2">{{ $cargo->descricao }}</textarea>
    </div>
    <div class="row mt-2">
        <div class="col-sm-6 form-group">
            <label for="faixa_min" class="text-semibold small">Faixa mínima (R$)</label>
            <input id="faixa_min" type="number" step="0.01" min="0" name="faixa_min" class="form-control" value="{{ $cargo->faixa_min }}">
        </div>
        <div class="col-sm-6 form-group">
            <label for="faixa_max" class="text-semibold small">Faixa máxima (R$)</label>
            <input id="faixa_max" type="number" step="0.01" min="0" name="faixa_max" class="form-control" value="{{ $cargo->faixa_max }}">
        </div>
    </div>
    <p class="text-muted small mb-0">A remuneração fora da faixa gera um aviso, não bloqueia.</p>
    @if ($beneficios->isNotEmpty())
        <div class="form-group mt-3">
            <label for="beneficios_padrao" class="text-semibold small">Benefícios padrão do cargo</label>
            <input type="hidden" name="beneficios_padrao_enviado" value="1">
            <select id="beneficios_padrao" name="beneficios_padrao[]" class="form-select" multiple size="4">
                @foreach ($beneficios as $beneficio)
                    <option value="{{ $beneficio->id }}" @selected(in_array($beneficio->id, $beneficiosPadrao, true))>{{ $beneficio->nome }}</option>
                @endforeach
            </select>
            <p class="text-muted small mb-0 mt-1">Aplicados ao colaborador na conclusão da admissão (Ctrl ou Cmd para vários).</p>
        </div>
    @endif
    @if ($cargo->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($cargo->ativo)>
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
    @endif
@endsection

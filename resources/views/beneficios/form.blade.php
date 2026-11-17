@extends('layouts.modal', [
    'title' => $beneficio->exists ? 'Editar benefício' : 'Novo benefício',
    'formroute' => route('beneficio.store', $beneficio->exists ? [$beneficio->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="form-group">
        <label for="nome" class="text-semibold small">Nome do benefício</label>
        <input id="nome" type="text" name="nome" class="form-control" value="{{ $beneficio->nome }}" required>
    </div>
    <div class="form-group mt-2">
        <label for="tipo_calculo" class="text-semibold small">Como é calculado</label>
        <select id="tipo_calculo" name="tipo_calculo" class="form-select" required>
            @foreach ($tipos as $tipo)
                <option value="{{ $tipo->value }}" @selected($beneficio->tipo_calculo === $tipo)>{{ $tipo->rotulo() }}</option>
            @endforeach
        </select>
        <p class="text-muted small mb-0 mt-1">Fixo mensal: valor cheio no mês. Por dia trabalhado: valor do dia vezes os dias trabalhados (calculado no fechamento do mês).</p>
    </div>
    @if ($beneficio->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($beneficio->ativo)>
            <label for="ativo" class="form-check-label">Ativo</label>
        </div>
    @endif
@endsection

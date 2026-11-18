@extends('layouts.modal', [
    'title' => $ocorrencia->exists ? 'Editar ocorrência' : 'Nova ocorrência',
    'formroute' => route('ocorrencia.store', $ocorrencia->exists ? [$ocorrencia->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <label for="colaborador_id" class="text-semibold small">Colaborador</label>
            @if ($ocorrencia->exists)
                <input type="text" class="form-control" value="{{ $ocorrencia->colaborador->pessoaFisica->nome }}" disabled>
            @else
                <select id="colaborador_id" name="colaborador_id" class="form-select" required>
                    <option value="">Selecione</option>
                    @foreach ($colaboradores as $c)
                        <option value="{{ $c->id }}">{{ $c->pessoaFisica->nome }}</option>
                    @endforeach
                </select>
            @endif
        </div>
        <div class="col-sm-4">
            <label for="tipo" class="text-semibold small">Tipo</label>
            <select id="tipo" name="tipo" class="form-select" required>
                @foreach ($tipos as $t)
                    <option value="{{ $t->value }}" @selected($ocorrencia->tipo === $t)>{{ $t->rotulo() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4">
            <label for="inicio" class="text-semibold small">Início</label>
            <input id="inicio" type="date" name="inicio" class="form-control" value="{{ $ocorrencia->inicio?->toDateString() }}" required>
        </div>
        <div class="col-sm-4">
            <label for="fim" class="text-semibold small">Fim (inclusive)</label>
            <input id="fim" type="date" name="fim" class="form-control" value="{{ $ocorrencia->fim?->toDateString() }}" required>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="abona" value="0">
                <input id="abona" type="checkbox" role="switch" class="form-check-input" name="abona" value="1" @checked($ocorrencia->abona)>
                <label for="abona" class="form-check-label">Abonada (não desconta do benefício por dia)</label>
            </div>
        </div>
        <div class="col-12">
            <label for="observacao" class="text-semibold small">Observação (opcional)</label>
            <input id="observacao" type="text" name="observacao" class="form-control" value="{{ $ocorrencia->observacao }}" maxlength="255">
        </div>
    </div>
@endsection

@extends('layouts.modal', [
    'title' => $feriado->exists ? 'Editar feriado' : 'Novo feriado',
    'formroute' => route('feriado.store', $feriado->exists ? [$feriado->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-sm-4">
            <label for="data" class="text-semibold small">Data</label>
            <input id="data" type="date" name="data" class="form-control" value="{{ $feriado->data?->toDateString() }}" required>
        </div>
        <div class="col-sm-8">
            <label for="descricao" class="text-semibold small">Descrição</label>
            <input id="descricao" type="text" name="descricao" class="form-control" value="{{ $feriado->descricao }}" maxlength="255" required>
        </div>
        <div class="col-sm-5">
            <label for="abrangencia" class="text-semibold small">Abrangência</label>
            <select id="abrangencia" name="abrangencia" class="form-select" required>
                @foreach ($abrangencias as $a)
                    <option value="{{ $a->value }}" @selected($feriado->abrangencia === $a)>{{ $a->rotulo() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-7" id="bloco-obra" {{ $feriado->abrangencia === \App\Enums\AbrangenciaFeriado::Nacional ? 'hidden' : '' }}>
            <label for="obra_id" class="text-semibold small">Obra a que se aplica</label>
            <select id="obra_id" name="obra_id" class="form-select">
                <option value="">Selecione</option>
                @foreach ($obras as $o)
                    <option value="{{ $o->id }}" @selected($feriado->obra_id === $o->id)>{{ ($o->codigo ? $o->codigo.' · ' : '').$o->descricao }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <script>
        document.getElementById('abrangencia').addEventListener('change', function () {
            const nacional = this.value === 'nacional';
            document.getElementById('bloco-obra').hidden = nacional;
            if (nacional) { document.getElementById('obra_id').value = ''; }
        });
    </script>
@endsection

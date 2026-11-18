@extends('layouts.modal', [
    'title' => $nivel->exists ? 'Editar nível' : 'Novo nível',
    'formroute' => route('ferias-nivel.store', $nivel->exists ? [$nivel->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-sm-3">
            <label for="ordem" class="text-semibold small">Ordem</label>
            <input id="ordem" type="number" name="ordem" min="1" max="20" class="form-control" value="{{ $nivel->ordem }}" required>
        </div>
        <div class="col-sm-9">
            <label for="tipo_aprovador" class="text-semibold small">Quem aprova</label>
            <select id="tipo_aprovador" name="tipo_aprovador" class="form-select" required>
                @foreach ($tipos as $t)
                    <option value="{{ $t->value }}" @selected($nivel->tipo_aprovador === $t)>{{ $t->rotulo() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12" id="bloco-permissao" {{ $nivel->tipo_aprovador === \App\Enums\TipoAprovadorFerias::GestorEquipe ? 'hidden' : '' }}>
            <label for="permission_id" class="text-semibold small">Permissão</label>
            <select id="permission_id" name="permission_id" class="form-select">
                <option value="">Selecione</option>
                @foreach ($permissoes as $p)
                    <option value="{{ $p->id }}" @selected($nivel->permission_id === $p->id)>{{ $p->display_name }} ({{ $p->name }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="ativo" value="0">
                <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($nivel->ativo)>
                <label for="ativo" class="form-check-label">Ativo</label>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('tipo_aprovador').addEventListener('change', function () {
            document.getElementById('bloco-permissao').hidden = this.value === 'gestor_equipe';
        });
    </script>
@endsection

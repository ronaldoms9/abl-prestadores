@extends('layouts.modal', [
    'title' => $fluxo->exists ? 'Editar fluxo' : 'Novo fluxo',
    'formroute' => route('ferias-fluxo.store', $fluxo->exists ? [$fluxo->id] : []),
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <label for="nome" class="text-semibold small">Nome</label>
            <input id="nome" type="text" name="nome" maxlength="120" class="form-control" value="{{ $fluxo->nome }}" placeholder="Ex.: Gerentes da equipe Norte" required>
        </div>
        @unless ($fluxo->ePadrao())
            <div class="col-12">
                <label for="escopo" class="text-semibold small">Vale para</label>
                <select id="escopo" name="escopo" class="form-select" required>
                    @foreach ($escopos as $e)
                        <option value="{{ $e->value }}" @selected($fluxo->escopo === $e)>{{ $e->rotulo() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12" data-escopo="equipe equipe_cargo">
                <label for="equipe_id" class="text-semibold small">Equipe</label>
                <select id="equipe_id" name="equipe_id" class="form-select">
                    <option value="">Selecione</option>
                    @foreach ($equipes as $e)
                        <option value="{{ $e->id }}" @selected($fluxo->equipe_id === $e->id)>{{ $e->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12" data-escopo="cargo equipe_cargo">
                <label for="cargo_id" class="text-semibold small">Função (cargo)</label>
                <select id="cargo_id" name="cargo_id" class="form-select">
                    <option value="">Selecione</option>
                    @foreach ($cargos as $c)
                        <option value="{{ $c->id }}" @selected($fluxo->cargo_id === $c->id)>{{ $c->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12" data-escopo="colaborador">
                <label for="colaborador_id" class="text-semibold small">Colaborador</label>
                <select id="colaborador_id" name="colaborador_id" class="form-select">
                    <option value="">Selecione</option>
                    @foreach ($colaboradores as $c)
                        <option value="{{ $c->id }}" @selected($fluxo->colaborador_id === $c->id)>{{ $c->pessoaFisica?->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="ativo" value="0">
                    <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($fluxo->ativo)>
                    <label for="ativo" class="form-check-label">Ativo</label>
                </div>
            </div>
        @endunless
    </div>
    @unless ($fluxo->ePadrao())
        <script>
            (function () {
                var escopo = document.getElementById('escopo');
                function mostrar() {
                    document.querySelectorAll('[data-escopo]').forEach(function (el) {
                        el.hidden = el.getAttribute('data-escopo').split(' ').indexOf(escopo.value) === -1;
                    });
                }
                escopo.addEventListener('change', mostrar);
                mostrar();
            })();
        </script>
    @endunless
@endsection

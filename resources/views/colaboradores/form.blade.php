@extends('layouts.modal', [
    'title' => $colaborador->exists ? 'Editar colaborador' : 'Novo colaborador',
    'formroute' => route('colaborador.store', $colaborador->exists ? [$colaborador->id] : []),
    'size' => 'modal-lg',
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    {{-- Pessoa física: escolhida na criação e fixa depois (outra pessoa é outro colaborador). --}}
    <div class="form-group mb-3 position-relative">
        <label for="pf-busca" class="text-semibold small">Pessoa física</label>
        @if ($colaborador->exists)
            <input type="text" class="form-control" value="{{ $colaborador->pessoaFisica->nome }} · {{ $colaborador->pessoaFisica->cpf_formatado }}" disabled>
        @else
            <input type="hidden" id="pessoa_fisica_id" name="pessoa_fisica_id">
            <input id="pf-busca" type="search" class="form-control" autocomplete="off" placeholder="Nome ou CPF (ao menos 3 caracteres)">
            <div id="pf-opcoes" class="list-group position-absolute shadow" style="z-index: 5; max-width: 95%"></div>
            <span class="text-muted small">Cadastre a pessoa em Pessoas físicas antes, se ela ainda não existir.</span>
        @endif
    </div>

    <div class="form-group mb-3 position-relative">
        <label for="pj-busca" class="text-semibold small">Pessoa jurídica pela qual presta serviço</label>
        <input type="hidden" id="pessoa_juridica_id" name="pessoa_juridica_id" value="{{ $colaborador->pessoa_juridica_id }}">
        <input id="pj-busca" type="search" class="form-control" autocomplete="off" placeholder="Razão social, nome fantasia ou CNPJ"
               value="{{ $colaborador->pessoaJuridica ? $colaborador->pessoaJuridica->razao_social.' · '.$colaborador->pessoaJuridica->cnpj_formatado : '' }}">
        <div id="pj-opcoes" class="list-group position-absolute shadow" style="z-index: 5; max-width: 95%"></div>
        <div id="pj-sugestoes" class="small mt-1"></div>
    </div>

    <div class="row">
        <div class="col-sm-4 form-group">
            <label for="cargo_id" class="text-semibold small">Cargo</label>
            <select id="cargo_id" name="cargo_id" class="form-select">
                <option value="">Sem cargo</option>
                @foreach ($cargos as $cargo)
                    <option value="{{ $cargo->id }}" @selected($colaborador->cargo_id === $cargo->id)>{{ $cargo->nome }}</option>
                @endforeach
            </select>
            @if ($cargos->isEmpty())<span class="text-muted small">Nenhum cargo cadastrado ainda.</span>@endif
        </div>
        <div class="col-sm-4 form-group">
            <label for="empresa_id" class="text-semibold small">Empresa</label>
            <select id="empresa_id" name="empresa_id" class="form-select">
                <option value="">Sem empresa</option>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}" @selected($colaborador->empresa_id === $empresa->id)>{{ $empresa->razao_social }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4 form-group">
            <label for="obra_id" class="text-semibold small">Obra (centro de custo)</label>
            <select id="obra_id" name="obra_id" class="form-select" @disabled($colaborador->exists && $colaborador->obras()->vigentesEm(now())->count() > 1)>
                <option value="">Sem obra</option>
                @foreach ($obras as $obra)
                    <option value="{{ $obra->id }}" data-empresa="{{ $obra->empresa_id }}" @selected($colaborador->obra_id === $obra->id)>{{ $obra->descricao }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="text-muted small mb-0 mt-2">O colaborador nasce em pré-admissão. A admissão (checklist e contrato) é feita na ficha do colaborador.</p>

    @push('scripts')
    <script>
        (() => {
            const buscador = (idBusca, idOpcoes, idCampo, url, aoEscolher) => {
                const busca = document.getElementById(idBusca);
                if (!busca) return;
                const opcoes = document.getElementById(idOpcoes);
                const campo = document.getElementById(idCampo);
                let espera;
                busca.addEventListener('input', () => {
                    campo.value = '';
                    clearTimeout(espera);
                    const q = busca.value.trim();
                    if (q.length < 3) { opcoes.replaceChildren(); return; }
                    espera = setTimeout(async () => {
                        const r = await fetch(url + '?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}});
                        const itens = await r.json();
                        opcoes.replaceChildren(...itens.map((i) => {
                            const b = document.createElement('button');
                            b.type = 'button'; b.className = 'list-group-item list-group-item-action'; b.textContent = i.texto;
                            b.addEventListener('click', () => { campo.value = i.id; busca.value = i.texto; opcoes.replaceChildren(); aoEscolher && aoEscolher(i); });
                            return b;
                        }));
                    }, 300);
                });
            };

            const sugestoes = document.getElementById('pj-sugestoes');
            const mostrarSugestoes = async (pf) => {
                sugestoes.replaceChildren();
                const r = await fetch(@json(route('colaborador.sugestoes')) + '?pessoa_fisica_id=' + pf.id, {headers: {'Accept': 'application/json'}});
                const itens = await r.json();
                if (!itens.length) return;
                sugestoes.append('Sugerida pela planilha: ');
                itens.forEach((i) => {
                    const a = document.createElement('a');
                    a.href = '#'; a.textContent = i.texto; a.className = 'me-2';
                    a.addEventListener('click', (ev) => { ev.preventDefault(); document.getElementById('pessoa_juridica_id').value = i.id; document.getElementById('pj-busca').value = i.texto; });
                    sugestoes.append(a);
                });
            };

            buscador('pf-busca', 'pf-opcoes', 'pessoa_fisica_id', @json(route('pessoa-fisica.buscar')), mostrarSugestoes);
            buscador('pj-busca', 'pj-opcoes', 'pessoa_juridica_id', @json(route('pessoa-juridica.buscar')));

            // A obra só lista as da empresa escolhida.
            const empresa = document.getElementById('empresa_id');
            const obra = document.getElementById('obra_id');
            const filtrarObras = () => {
                [...obra.options].forEach((o) => { o.hidden = !!o.value && !!empresa.value && o.dataset.empresa !== empresa.value; });
                if (obra.selectedOptions[0]?.hidden) obra.value = '';
            };
            empresa.addEventListener('change', filtrarObras);
            filtrarObras();
        })();
    </script>
    @endpush
@endsection

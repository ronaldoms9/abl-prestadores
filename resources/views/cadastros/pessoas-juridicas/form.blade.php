@extends('layouts.modal', [
    'title' => $pessoa->exists ? 'Editar pessoa jurídica' : 'Nova pessoa jurídica',
    'formroute' => route('pessoa-juridica.store', $pessoa->exists ? [$pessoa->id] : []),
    'size' => 'modal-xl',
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Salvar <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    @if ($pessoa->uau_codigo)
        <div class="alert alert-warning small">
            Esta pessoa vem do UAU (código {{ $pessoa->uau_codigo }}). Enquanto a gravação no UAU não estiver ligada,
            a sincronização diária pode desfazer as alterações feitas aqui.
        </div>
    @endif

    <div class="row">
        <div class="col-sm-4 form-group">
            <label for="cnpj" class="text-semibold small">CNPJ</label>
            <input id="cnpj" type="text" name="cnpj" class="form-control" value="{{ $pessoa->cnpj_formatado }}" inputmode="numeric" required>
        </div>
        <div class="col-sm-8 form-group">
            <label for="razao_social" class="text-semibold small">Razão social</label>
            <input id="razao_social" type="text" name="razao_social" class="form-control" value="{{ $pessoa->razao_social }}" required>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-5 form-group">
            <label for="nome_fantasia" class="text-semibold small">Nome fantasia</label>
            <input id="nome_fantasia" type="text" name="nome_fantasia" class="form-control" value="{{ $pessoa->nome_fantasia }}">
        </div>
        <div class="col-sm-3 form-group">
            <label for="regime_tributario" class="text-semibold small">Regime tributário</label>
            <select id="regime_tributario" name="regime_tributario" class="form-select">
                <option value=""></option>
                @foreach ($regimes as $regime)
                    <option value="{{ $regime }}" @selected($pessoa->regime_tributario === $regime)>{{ $regime }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2 form-group">
            <label for="inscricao_municipal" class="text-semibold small">Insc. municipal</label>
            <input id="inscricao_municipal" type="text" name="inscricao_municipal" class="form-control" value="{{ $pessoa->inscricao_municipal }}">
        </div>
        <div class="col-sm-2 form-group">
            <label for="inscricao_estadual" class="text-semibold small">Insc. estadual</label>
            <input id="inscricao_estadual" type="text" name="inscricao_estadual" class="form-control" value="{{ $pessoa->inscricao_estadual }}">
        </div>
    </div>
    <div class="row">
        <div class="col-sm-8 form-group">
            <label for="email" class="text-semibold small">E-mail</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ $pessoa->email }}">
        </div>
        <div class="col-sm-4 form-group">
            <label for="telefone" class="text-semibold small">Telefone</label>
            <input id="telefone" type="text" name="telefone" class="form-control" value="{{ $pessoa->telefone }}" inputmode="tel">
        </div>
    </div>

    <h6 class="text-semibold mt-2">Pessoa física responsável</h6>
    <div class="row">
        <div class="col-sm-8 form-group">
            <input type="hidden" id="responsavel_id" name="responsavel_id" value="{{ $pessoa->responsavelAtual?->pessoa_fisica_id }}">
            <label for="responsavel-busca" class="text-semibold small">Buscar por nome ou CPF</label>
            <input id="responsavel-busca" type="search" class="form-control" autocomplete="off"
                   placeholder="Digite ao menos 3 letras ou dígitos"
                   value="{{ $pessoa->responsavelAtual ? $pessoa->responsavelAtual->pessoaFisica->nome.' · '.$pessoa->responsavelAtual->pessoaFisica->cpf_formatado : '' }}">
            <div id="responsavel-opcoes" class="list-group position-absolute shadow" style="z-index: 5; max-width: 90%"></div>
            <span id="responsavel-aviso" class="text-muted small">
                @if ($pessoa->responsavelAtual) Responsável desde {{ $pessoa->responsavelAtual->inicio->format('d/m/Y') }}. @endif
                Obrigatório. O vínculo fica só neste sistema, não vai para o UAU.
            </span>
        </div>
    </div>

    @include('cadastros._form-endereco')
    @include('cadastros._form-bancos')

    @if ($pessoa->exists)
        <div class="form-check form-switch mt-2">
            <input type="hidden" name="ativo" value="0">
            <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($pessoa->ativo)>
            <label for="ativo" class="form-check-label">Ativa</label>
        </div>
    @endif

    @push('scripts')
    <script>
        (() => {
            const busca = document.getElementById('responsavel-busca');
            const opcoes = document.getElementById('responsavel-opcoes');
            const campo = document.getElementById('responsavel_id');
            let espera;
            busca.addEventListener('input', () => {
                campo.value = '';
                clearTimeout(espera);
                const q = busca.value.trim();
                if (q.length < 3) { opcoes.replaceChildren(); return; }
                espera = setTimeout(async () => {
                    const r = await fetch(@json(route('pessoa-fisica.buscar')) + '?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}});
                    const itens = await r.json();
                    opcoes.replaceChildren(...itens.map((i) => {
                        const b = document.createElement('button');
                        b.type = 'button'; b.className = 'list-group-item list-group-item-action'; b.textContent = i.texto;
                        b.addEventListener('click', () => { campo.value = i.id; busca.value = i.texto; opcoes.replaceChildren(); });
                        return b;
                    }));
                    if (!itens.length) {
                        const v = document.createElement('div'); v.className = 'list-group-item text-muted small';
                        v.textContent = 'Nenhuma pessoa física ativa encontrada. Cadastre-a antes.'; opcoes.replaceChildren(v);
                    }
                }, 300);
            });
        })();
    </script>
    @endpush
@endsection

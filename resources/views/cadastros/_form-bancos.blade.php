{{-- Contas bancárias da pessoa: várias, uma principal. Linhas novas vêm do modelo (template). --}}
@php
    $contas = $pessoa->dadosBancarios ?? collect();
    $linhas = $contas->map(fn ($c) => [
        'id' => $c->id, 'banco' => $c->banco, 'agencia' => $c->agencia, 'conta' => $c->conta,
        'tipo' => $c->tipo->value, 'tipo_chave_pix' => $c->tipo_chave_pix?->value, 'chave_pix' => $c->chave_pix,
        'principal' => $c->principal,
    ])->values();
    $tiposConta = \App\Enums\TipoContaBancaria::cases();
    $tiposPix = \App\Enums\TipoChavePix::cases();
@endphp
<h6 class="text-semibold mt-2">Dados bancários</h6>
<div id="bancos-lista"></div>
<button type="button" id="bancos-add" class="btn btn-outline-primary btn-sm mb-2"><i class="fa fa-plus"></i> Adicionar conta</button>

<template id="banco-modelo">
    <div class="border rounded p-2 mb-2 banco-linha">
        <input type="hidden" data-campo="id">
        <div class="row">
            <div class="col-sm-4 form-group"><label class="small text-semibold">Banco</label><input type="text" class="form-control" data-campo="banco"></div>
            <div class="col-sm-2 form-group"><label class="small text-semibold">Agência</label><input type="text" class="form-control" data-campo="agencia"></div>
            <div class="col-sm-3 form-group"><label class="small text-semibold">Conta</label><input type="text" class="form-control" data-campo="conta"></div>
            <div class="col-sm-3 form-group"><label class="small text-semibold">Tipo</label>
                <select class="form-select" data-campo="tipo">
                    @foreach ($tiposConta as $t)<option value="{{ $t->value }}">{{ $t->rotulo() }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="row align-items-end">
            <div class="col-sm-3 form-group"><label class="small text-semibold">Tipo da chave PIX</label>
                <select class="form-select" data-campo="tipo_chave_pix">
                    <option value="">Sem PIX</option>
                    @foreach ($tiposPix as $t)<option value="{{ $t->value }}">{{ $t->rotulo() }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-5 form-group"><label class="small text-semibold">Chave PIX</label><input type="text" class="form-control" data-campo="chave_pix"></div>
            <div class="col-sm-2 form-group">
                <div class="form-check"><input class="form-check-input" type="radio" name="principal" data-campo="principal"><label class="form-check-label small">Principal</label></div>
            </div>
            <div class="col-sm-2 form-group text-end"><button type="button" class="btn btn-link text-danger btn-sm" data-remover>Remover</button></div>
        </div>
    </div>
</template>

@push('scripts')
<script>
    (() => {
        const lista = document.getElementById('bancos-lista');
        const modelo = document.getElementById('banco-modelo');
        const inicial = @json($linhas);

        const renumerar = () => {
            lista.querySelectorAll('.banco-linha').forEach((linha, i) => {
                linha.querySelectorAll('[data-campo]').forEach((el) => {
                    const campo = el.dataset.campo;
                    if (campo === 'principal') { el.value = i; return; }
                    el.name = `bancos[${i}][${campo}]`;
                });
            });
            if (lista.querySelector('[data-campo=principal]') && !lista.querySelector('[data-campo=principal]:checked')) {
                lista.querySelector('[data-campo=principal]').checked = true;
            }
        };
        const adicionar = (d = {}) => {
            const no = modelo.content.firstElementChild.cloneNode(true);
            no.querySelectorAll('[data-campo]').forEach((el) => {
                const v = d[el.dataset.campo];
                if (el.dataset.campo === 'principal') { el.checked = !!v; return; }
                if (v !== undefined && v !== null) el.value = v;
            });
            no.querySelector('[data-remover]').addEventListener('click', () => { no.remove(); renumerar(); });
            lista.appendChild(no);
            renumerar();
        };
        document.getElementById('bancos-add').addEventListener('click', () => adicionar());
        inicial.forEach(adicionar);
    })();
</script>
@endpush

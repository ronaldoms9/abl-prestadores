{{-- Endereço em campos separados; o CEP preenche o resto pela ViaCEP (falha não impede o cadastro manual). --}}
@php $e = $pessoa->endereco; @endphp
<h6 class="text-semibold mt-2">Endereço</h6>
<div class="row">
    <div class="col-sm-3 form-group">
        <label for="end-cep" class="text-semibold small">CEP</label>
        <input id="end-cep" type="text" name="endereco[cep]" class="form-control" maxlength="9"
               value="{{ $e?->cep }}" inputmode="numeric" autocomplete="postal-code">
        <span id="end-cep-aviso" class="text-muted small"></span>
    </div>
    <div class="col-sm-6 form-group">
        <label for="end-logradouro" class="text-semibold small">Logradouro</label>
        <input id="end-logradouro" type="text" name="endereco[logradouro]" class="form-control" value="{{ $e?->logradouro }}">
    </div>
    <div class="col-sm-3 form-group">
        <label for="end-numero" class="text-semibold small">Número</label>
        <input id="end-numero" type="text" name="endereco[numero]" class="form-control" value="{{ $e?->numero }}">
    </div>
</div>
<div class="row">
    <div class="col-sm-4 form-group">
        <label for="end-complemento" class="text-semibold small">Complemento</label>
        <input id="end-complemento" type="text" name="endereco[complemento]" class="form-control" value="{{ $e?->complemento }}">
    </div>
    <div class="col-sm-3 form-group">
        <label for="end-bairro" class="text-semibold small">Bairro</label>
        <input id="end-bairro" type="text" name="endereco[bairro]" class="form-control" value="{{ $e?->bairro }}">
    </div>
    <div class="col-sm-3 form-group">
        <label for="end-cidade" class="text-semibold small">Cidade</label>
        <input id="end-cidade" type="text" name="endereco[cidade]" class="form-control" value="{{ $e?->cidade }}">
    </div>
    <div class="col-sm-2 form-group">
        <label for="end-uf" class="text-semibold small">UF</label>
        <select id="end-uf" name="endereco[uf]" class="form-select">
            <option value=""></option>
            @foreach (\App\Support\RegrasCadastro::UFS as $uf)
                <option value="{{ $uf }}" @selected($e?->uf === $uf)>{{ $uf }}</option>
            @endforeach
        </select>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const cep = document.getElementById('end-cep');
        if (!cep) return;
        const aviso = document.getElementById('end-cep-aviso');
        cep.addEventListener('blur', async () => {
            const digitos = cep.value.replace(/\D/g, '');
            aviso.textContent = '';
            if (digitos.length !== 8) return;
            try {
                const r = await fetch(`https://viacep.com.br/ws/${digitos}/json/`);
                const d = await r.json();
                if (d.erro) { aviso.textContent = 'CEP não encontrado. Preencha o endereço manualmente.'; return; }
                const poe = (id, v) => { const el = document.getElementById(id); if (el && !el.value && v) el.value = v; };
                poe('end-logradouro', d.logradouro); poe('end-bairro', d.bairro); poe('end-cidade', d.localidade);
                const uf = document.getElementById('end-uf'); if (uf && !uf.value && d.uf) uf.value = d.uf;
            } catch (e) {
                aviso.textContent = 'Não foi possível consultar o CEP. Preencha o endereço manualmente.';
            }
        });
    })();
</script>
@endpush

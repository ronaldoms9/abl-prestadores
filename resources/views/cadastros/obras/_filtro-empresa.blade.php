<div class="col-sm-12 col-lg-4">
    <label for="empresa" class="small text-semibold">Empresa</label>
    <select id="empresa" name="empresa" class="form-select form-select-sm">
        <option value="">Todas</option>
        @foreach ($empresas as $empresa)
            <option value="{{ $empresa->id }}">{{ $empresa->uau_codigo }} · {{ $empresa->razao_social }}{{ $empresa->ativo ? '' : ' (inativa)' }}</option>
        @endforeach
    </select>
</div>

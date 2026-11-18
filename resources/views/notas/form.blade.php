@extends('layouts.modal', [
    'title' => 'Nota de '.substr($fechamento->competencia->ano_mes, 5, 2).'/'.substr($fechamento->competencia->ano_mes, 0, 4).' · '.$fechamento->colaborador->pessoaFisica->nome,
    'formroute' => $acao,
])

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
    <button type="submit" class="btn btn-primary pull-right">Enviar nota <i class="fa fa-arrow-right"></i></button>
@endsection

@section('content')
    <p class="small mb-2">Valor esperado: <strong>R$ {{ number_format((float) $fechamento->total_liquido, 2, ',', '.') }}</strong>
        @if ($fechamento->nota?->motivo_recusa)<br><span class="text-danger">Recusada antes: {{ $fechamento->nota->motivo_recusa }}</span>@endif</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="nf-xml" class="text-semibold small">XML da nota (se houver)</label>
            <input id="nf-xml" type="file" name="xml" accept=".xml,text/xml,application/xml" class="form-control form-control-sm">
        </div>
        <div class="col-md-6">
            <label for="nf-pdf" class="text-semibold small">PDF da nota</label>
            <input id="nf-pdf" type="file" name="pdf" accept="application/pdf" class="form-control form-control-sm">
        </div>
    </div>
    <div id="nf-lido" class="alert alert-success py-2 small mt-3" hidden></div>
    <div id="nf-erro" class="alert alert-warning py-2 small mt-3" hidden></div>

    <div class="form-check form-switch mt-3">
        <input id="nf-manual-toggle" type="checkbox" role="switch" class="form-check-input">
        <label for="nf-manual-toggle" class="form-check-label">Informar os dados à mão (sem XML ou se o XML não for lido)</label>
    </div>
    <div id="nf-manual" class="row g-2 mt-1" hidden>
        <div class="col-sm-3"><label for="nf-numero" class="small text-semibold">Número</label><input id="nf-numero" type="text" name="numero" class="form-control form-control-sm" maxlength="30"></div>
        <div class="col-sm-2"><label for="nf-serie" class="small text-semibold">Série</label><input id="nf-serie" type="text" name="serie" class="form-control form-control-sm" maxlength="10"></div>
        <div class="col-sm-3"><label for="nf-emissao" class="small text-semibold">Emissão</label><input id="nf-emissao" type="date" name="data_emissao" class="form-control form-control-sm"></div>
        <div class="col-sm-4"><label for="nf-valor" class="small text-semibold">Valor bruto (R$)</label><input id="nf-valor" type="number" step="0.01" min="0" name="valor_bruto" class="form-control form-control-sm"></div>
        <div class="col-sm-6"><label for="nf-emissor" class="small text-semibold">CNPJ do emissor</label><input id="nf-emissor" type="text" name="cnpj_emissor" class="form-control form-control-sm" maxlength="20"></div>
        <div class="col-sm-6"><label for="nf-tomador" class="small text-semibold">CNPJ do tomador</label><input id="nf-tomador" type="text" name="cnpj_tomador" class="form-control form-control-sm" maxlength="20"></div>
        <div class="col-12"><label for="nf-chave" class="small text-semibold">Chave de acesso (se houver)</label><input id="nf-chave" type="text" name="chave_acesso" class="form-control form-control-sm" maxlength="70"></div>
        @foreach (['IRRF' => 'IRRF retido', 'INSS' => 'INSS retido', 'ISS' => 'ISS retido', 'PIS' => 'PIS retido', 'COFINS' => 'COFINS retido', 'CSLL' => 'CSLL retido'] as $tributo => $rotulo)
            <div class="col-sm-4"><label class="small text-semibold">{{ $rotulo }} (R$)</label><input type="number" step="0.01" min="0" name="retencoes[{{ $tributo }}]" class="form-control form-control-sm"></div>
        @endforeach
    </div>
    <script>
        (() => {
            const xml = document.getElementById('nf-xml');
            const lido = document.getElementById('nf-lido');
            const erro = document.getElementById('nf-erro');
            const manual = document.getElementById('nf-manual');
            const toggle = document.getElementById('nf-manual-toggle');
            toggle.addEventListener('change', () => { manual.hidden = !toggle.checked; });
            xml.addEventListener('change', () => {
                lido.hidden = true; erro.hidden = true;
                if (!xml.files.length) return;
                const dados = new FormData(); dados.append('xml', xml.files[0]);
                axios.post(@json($previa), dados).then((r) => {
                    const d = r.data.data;
                    lido.textContent = 'XML lido (' + d.layout + '): nota ' + (d.numero || '—') + ', emissão ' + (d.emissao || '—') + ', valor ' + (d.valor_bruto || '—') + (d.retencoes ? ', retenções ' + d.retencoes : '') + '. Confira e envie.';
                    lido.hidden = false;
                }).catch((e) => {
                    erro.textContent = (e.response?.data?.message || 'Não foi possível ler o XML.') + ' Informe os dados à mão.';
                    erro.hidden = false; toggle.checked = true; manual.hidden = false;
                });
            });
        })();
    </script>
@endsection

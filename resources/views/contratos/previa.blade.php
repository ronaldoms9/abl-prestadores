@extends('layouts.modal', ['title' => 'Pré-visualização · '.$contrato->tipo->rotulo(), 'size' => 'modal-xl'])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    @if ($resultado === null)
        <p class="text-muted">Este documento já foi gerado ({{ $contrato->numero }}).</p>
        <iframe sandbox="" style="width: 100%; min-height: 32rem; border: 1px solid #dee2e6; background: #fff" title="Documento gerado"
                srcdoc="{{ '<body style=&quot;font-family: serif; padding: 24px&quot;>'.$contrato->conteudo_renderizado.'</body>' }}"></iframe>
    @else
        @if (! $resultado->completo())
            <div class="alert alert-warning py-2 small">
                Faltam dados no cadastro de {{ $contrato->colaborador->pessoaFisica->nome }} para preencher: {{ implode(', ', $resultado->faltantes) }}.
                Complete o cadastro antes de gerar.
            </div>
        @endif
        <p class="text-muted small">O número ({{ $contrato->tipo->rotulo() }}) é atribuído quando o documento é gerado.</p>
        <iframe sandbox="" style="width: 100%; min-height: 32rem; border: 1px solid #dee2e6; background: #fff" title="Pré-visualização"
                srcdoc="{{ '<body style=&quot;font-family: serif; padding: 24px&quot;>'.$resultado->html.'</body>' }}"></iframe>
    @endif
@endsection

{{-- Faixa do ambiente de homologação (D9.1 da fatia 9): ninguém confunde com produção. --}}
@if (app()->environment('homologacao'))
    <div class="alert alert-warning rounded-0 mb-0 py-1 text-center small" role="status" id="faixa-homologacao">
        <i class="bi bi-cone-striped"></i>
        <strong>Ambiente de homologação.</strong> Use para testar; os dados daqui podem ser apagados.
    </div>
@endif

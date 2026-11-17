@if ($pessoa->dadosBancarios->isEmpty())
    <p class="text-muted mb-0">Nenhuma conta cadastrada.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Banco</th><th>Agência</th><th>Conta</th><th>Tipo</th><th>PIX</th><th></th></tr></thead>
            <tbody>
            @foreach ($pessoa->dadosBancarios as $conta)
                <tr>
                    <td>{{ $conta->banco }}</td>
                    <td>{{ $conta->agencia }}</td>
                    <td>{{ $conta->conta }}</td>
                    <td>{{ $conta->tipo->rotulo() }}</td>
                    <td>{{ $conta->chave_pix ? $conta->tipo_chave_pix?->rotulo().': '.$conta->chave_pix : '—' }}</td>
                    <td>@if ($conta->principal)<span class="badge text-bg-primary">Principal</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

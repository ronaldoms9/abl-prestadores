{{-- Vínculos sugeridos pela carga da planilha (fatia 5b). Aceitar ou descartar é da E2. --}}
@if ($pessoas->isEmpty())
    <p class="text-muted mb-0">Nenhuma. Os vínculos são sugeridos pela carga da planilha quando a linha traz o CPF e o CNPJ.</p>
@else
    <table class="table table-sm mb-0">
        <tbody>
            @foreach ($pessoas as $pessoaVinculada)
                <tr>
                    <td class="text-nowrap">{{ $documento($pessoaVinculada) }}</td>
                    <td>{{ $nome($pessoaVinculada) }}</td>
                    <td>@include('cadastros._situacao', ['ativo' => $pessoaVinculada->ativo])</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

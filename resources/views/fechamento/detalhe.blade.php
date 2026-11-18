@extends('layouts.modal', ['title' => $fechamento->colaborador->pessoaFisica->nome.' · '.substr($fechamento->competencia->ano_mes, 5, 2).'/'.substr($fechamento->competencia->ano_mes, 0, 4)])

@php
    $fmt = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $editavel = ! $fechamento->competencia->status->imutavel();
@endphp

@section('footer')
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    @foreach ($fechamento->snapshot['avisos'] ?? [] as $aviso)
        <div class="alert alert-warning py-2 small">{{ $aviso }}</div>
    @endforeach
    <p class="small text-muted mb-2">Período ativo: {{ \Carbon\Carbon::parse($fechamento->snapshot['periodo'][0] ?? $fechamento->competencia->ano_mes.'-01')->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($fechamento->snapshot['periodo'][1] ?? $fechamento->competencia->ano_mes.'-01')->format('d/m/Y') }}
        · {{ $fechamento->dias_periodo }} dia(s), {{ (int) $fechamento->dias_trabalhados }} trabalhado(s)</p>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Memória de cálculo</th><th class="text-end">Qtd.</th><th class="text-end">Unitário</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            @foreach ($fechamento->itens->sortBy('id') as $i)
                <tr>
                    <td>{{ $i->descricao }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $i->quantidade, 2, ',', '.'), '0'), ',') }}</td>
                    <td class="text-end">{{ $fmt($i->valor_unitario) }}</td>
                    <td class="text-end">{{ $fmt($i->valor_total) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="3" class="text-end">Total líquido</th><th class="text-end">{{ $fmt($fechamento->total_liquido) }}</th></tr></tfoot>
        </table>
    </div>

    <h6 class="text-semibold mt-3">Ajustes avulsos</h6>
    @if ($ajustes->isEmpty())
        <p class="text-muted small">Nenhum ajuste neste mês.</p>
    @else
        <ul class="list-unstyled small">
            @foreach ($ajustes as $a)
                <li>{{ $a->tipo->value === 'credito' ? '+' : '−' }} {{ $fmt($a->valor) }} · {{ $a->justificativa }}
                    @if ($editavel) @permission('fechamento-conferir')
                        <a href="javascript:deleteDefault('{{ route('fechamento.ajuste.delete', $a->id) }}')" class="text-danger ms-1" title="Remover" aria-label="Remover"><i class="bi bi-trash"></i></a>
                    @endpermission @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($editavel)
        @permission('fechamento-conferir')
            <form class="axios-form row g-2 align-items-end" action="{{ route('fechamento.ajuste.store', $fechamento->id) }}" method="post">
                @csrf
                <div class="col-sm-3">
                    <label for="aj-tipo" class="small text-semibold">Tipo</label>
                    <select id="aj-tipo" name="tipo" class="form-select form-select-sm">
                        @foreach ($tipos as $t)<option value="{{ $t->value }}">{{ $t->value === 'credito' ? 'Crédito' : 'Débito' }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-3"><label for="aj-valor" class="small text-semibold">Valor (R$)</label><input id="aj-valor" type="number" step="0.01" min="0.01" name="valor" class="form-control form-control-sm" required></div>
                <div class="col-sm-4"><label for="aj-just" class="small text-semibold">Justificativa</label><input id="aj-just" type="text" name="justificativa" maxlength="500" class="form-control form-control-sm" required></div>
                <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Lançar</button></div>
            </form>
            <button type="button" class="btn btn-link btn-sm mt-2 p-0" id="btn-recalcular" data-url="{{ route('fechamento.recalcular', $fechamento->id) }}">Recalcular este colaborador</button>
            <script>
                document.getElementById('btn-recalcular').addEventListener('click', function () {
                    axios.post(this.dataset.url, {}).then((r) => { showNotify('Sucesso', r.data.message); $('.modal').modal('hide'); setTimeout(() => location.reload(), 700); })
                        .catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro.', 'error'));
                });
            </script>
        @endpermission
    @endif
@endsection

{{-- Períodos aquisitivos e saldo de férias (E4, fatia 2). Espera $colaborador com feriasPeriodos. --}}
@php $periodos = $colaborador->feriasPeriodos; $hoje = now()->startOfDay(); @endphp
<h6 class="text-semibold mt-3">Férias</h6>
@if ($periodos->isEmpty())
    <p class="text-muted">
        @if ($colaborador->status === \App\Enums\StatusColaborador::Ativo)
            Nenhum período aquisitivo ainda. Eles são gerados todo dia a partir da data de admissão.
        @else
            Os períodos de férias começam na admissão.
        @endif
    </p>
@else
    @php $saldoTotal = $periodos->sum(fn ($p) => $p->saldo()); @endphp
    <p class="mb-1">Saldo total: <strong>{{ $saldoTotal }} dia(s)</strong></p>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Período aquisitivo</th><th class="text-end">Direito</th><th class="text-end">Utilizados</th><th class="text-end">Saldo</th><th>Limite de gozo</th></tr></thead>
            <tbody>
            @foreach ($periodos as $periodo)
                @php $vencido = $periodo->saldo() > 0 && $periodo->limite_gozo->lt($hoje); @endphp
                <tr>
                    <td class="text-nowrap">{{ $periodo->aquisitivo_inicio->format('d/m/Y') }} a {{ $periodo->aquisitivo_fim->format('d/m/Y') }}</td>
                    <td class="text-end">{{ $periodo->dias_direito }}</td>
                    <td class="text-end">{{ $periodo->dias_utilizados }}</td>
                    <td class="text-end">{{ $periodo->saldo() }}</td>
                    <td class="text-nowrap">{{ $periodo->limite_gozo->format('d/m/Y') }}
                        @if ($vencido)<span class="badge text-bg-danger">vencido</span>@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

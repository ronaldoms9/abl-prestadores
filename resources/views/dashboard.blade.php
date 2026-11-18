@extends('layouts.app', ['title' => 'Dashboard'])

@php
    $nomeDono = function ($dono) {
        return match (true) {
            $dono instanceof \App\Models\Colaborador => $dono->pessoaFisica?->nome,
            $dono instanceof \App\Models\PessoaFisica => $dono->nome,
            $dono instanceof \App\Models\PessoaJuridica => $dono->razao_social,
            default => '—',
        };
    };
    $docsVencidos = isset($d['documentos']) ? $d['documentos']['vencidos'] : collect();
    $docsAVencer = isset($d['documentos']) ? $d['documentos']['a_vencer'] : collect();
    $pendQualif = $d['qualificacoes'] ?? collect();
@endphp

@section('content')
    <div class="container-fluid">
        {{-- Alertas por e-mail que ninguém recebe (README D2): só o administrador de perfis vê. --}}
        @permission('grupo-edit')
            @foreach (\App\Support\AvisosAdmin::alertasSemDestinatario() as $permissao => $aviso)
                <div class="alert alert-warning">
                    <strong>Ninguém recebe o {{ $aviso['descricao'] }}.</strong>
                    Desde {{ \Carbon\Carbon::parse($aviso['desde'])->format('d/m/Y') }}, nenhum usuário ativo tem a permissão
                    <code>{{ $permissao }}</code>. Dê a permissão a um perfil em Configurações › Perfis.
                </div>
            @endforeach
        @endpermission


        @php $ind = $d['indicadores'] ?? []; $reais = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
        @if ($ind !== [])
            <div class="row g-3 mb-3">
                @isset($ind['movimento'])
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Movimento do mês</div>
                        <div class="fs-2 fw-semibold"><span class="text-success">+{{ $ind['movimento']['admissoes'] }}</span> <span class="text-danger">−{{ $ind['movimento']['desligamentos'] }}</span></div>
                        <div class="small text-muted">admissões e desligamentos</div>
                    </div></div></div>
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Ativos sem equipe</div>
                        <div class="fs-2 fw-semibold {{ $ind['sem_equipe'] > 0 ? 'text-warning' : '' }}">{{ $ind['sem_equipe'] }}</div>
                        <div class="small text-muted">férias não passam pelo gestor</div>
                    </div></div></div>
                @endisset
                @isset($ind['custo'])
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Custo do último mês fechado</div>
                        <div class="fs-2 fw-semibold">{{ $ind['custo']['ultimo_total'] === null ? '—' : $reais($ind['custo']['ultimo_total']) }}</div>
                        <div class="small text-muted">{{ $ind['custo']['ultimo_mes'] ?? 'nenhuma competência aprovada' }}</div>
                    </div></div></div>
                @endisset
                @isset($ind['notas_pendentes'])
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Notas pendentes</div>
                        <div class="fs-2 fw-semibold">{{ $ind['notas_pendentes'] }}</div>
                        <div class="small"><a href="{{ route('nota.index') }}">Ver notas</a></div>
                    </div></div></div>
                @endisset
                @isset($ind['integracoes_com_erro'])
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Integrações com erro</div>
                        <div class="fs-2 fw-semibold {{ $ind['integracoes_com_erro'] > 0 ? 'text-danger' : '' }}">{{ $ind['integracoes_com_erro'] }}</div>
                        <div class="small"><a href="{{ route('integracao.index') }}">Ver monitoramento</a></div>
                    </div></div></div>
                @endisset
                @isset($ind['contratos'])
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Contratos</div>
                        <div class="fs-2 fw-semibold"><span class="text-danger">{{ $ind['contratos']['vencidos'] }}</span> <span class="fs-6 text-muted">vencidos</span></div>
                        <div class="small text-muted">{{ $ind['contratos']['a_vencer'] }} vencem em 30 dias</div>
                    </div></div></div>
                @endisset
                @isset($ind['ferias'])
                    <div class="col-6 col-lg-3"><div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Férias perto do limite</div>
                        <div class="fs-2 fw-semibold"><span class="text-danger">{{ $ind['ferias']['vencidos'] }}</span> <span class="fs-6 text-muted">vencidos</span></div>
                        <div class="small text-muted">{{ $ind['ferias']['a_vencer'] }} a vencer em {{ $ind['ferias']['dias'] }} dias</div>
                    </div></div></div>
                @endisset
            </div>

            <div class="row g-3 mb-3">
                @if (! empty($ind['custo']['serie']))
                    <div class="col-lg-7"><div class="card shadow h-100">
                        <div class="card-header border-0"><h6 class="mb-0">Custo mensal (competências aprovadas)</h6></div>
                        <div class="card-body pt-0">
                            <canvas id="grafico-custo" height="110" role="img" aria-label="Custo mensal dos últimos meses"></canvas>
                            <table class="table table-sm mt-2 mb-0 small" id="tabela-custo">
                                <tbody>@foreach ($ind['custo']['serie'] as $p)<tr><td>{{ $p['mes'] }}</td><td class="text-end">{{ $reais($p['total']) }}</td></tr>@endforeach</tbody>
                            </table>
                        </div>
                    </div></div>
                @endif
                @if (! empty($ind['por_obra']))
                    <div class="col-lg-5"><div class="card shadow h-100">
                        <div class="card-header border-0"><h6 class="mb-0">Ativos por obra</h6></div>
                        <div class="card-body pt-0">
                            @php $max = max(array_column($ind['por_obra'], 'total')); @endphp
                            @foreach ($ind['por_obra'] as $l)
                                <div class="d-flex justify-content-between small"><span>{{ $l['obra'] }}</span><span class="text-semibold">{{ $l['total'] }}</span></div>
                                <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width: {{ round($l['total'] / $max * 100) }}%"></div></div>
                            @endforeach
                        </div>
                    </div></div>
                @endif
                @if (! empty($ind['contratos']['itens']))
                    <div class="col-12"><div class="card shadow">
                        <div class="card-header border-0"><h6 class="mb-0">Contratos que pedem atenção</h6></div>
                        <div class="card-body pt-0"><div class="table-responsive"><table class="table table-sm mb-0">
                            <thead><tr><th>Colaborador</th><th>Contrato</th><th>Fim</th><th></th></tr></thead>
                            <tbody>@foreach ($ind['contratos']['itens'] as $c)<tr><td>{{ $c['colaborador'] }}</td><td>{{ $c['numero'] }}</td><td>{{ $c['fim'] }}</td><td><span class="badge text-bg-{{ $c['dias'] < 0 ? 'danger' : 'warning' }}">{{ $c['dias'] < 0 ? 'Vencido' : ($c['dias'] === 0 ? 'Vence hoje' : 'Vence em '.$c['dias'].' dia(s)') }}</span></td></tr>@endforeach</tbody>
                        </table></div></div>
                    </div></div>
                @endif
            </div>
        @endif

        <div class="row g-3 mb-3">
            @isset($d['colaboradores'])
                <div class="col-6 col-lg-3">
                    <div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Colaboradores ativos</div>
                        <div class="fs-2 fw-semibold">{{ $d['colaboradores']['ativos'] }}</div>
                        <div class="small text-muted">{{ $d['colaboradores']['pre_admissao'] }} em pré-admissão · {{ $d['colaboradores']['desligados'] }} desligado(s)</div>
                    </div></div>
                </div>
            @endisset
            @isset($d['remuneracao_mensal'])
                <div class="col-6 col-lg-3">
                    <div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Remuneração mensal vigente</div>
                        <div class="fs-2 fw-semibold">{{ $d['remuneracao_mensal'] }}</div>
                        <div class="small text-muted">dos colaboradores ativos</div>
                    </div></div>
                </div>
            @endisset
            @isset($d['documentos'])
                <div class="col-6 col-lg-3">
                    <div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Documentos</div>
                        <div class="fs-2 fw-semibold"><span class="text-danger">{{ $docsVencidos->count() }}</span> <span class="fs-6 text-muted">vencidos</span></div>
                        <div class="small text-muted">{{ $docsAVencer->count() }} a vencer em {{ config('cadastros.alertas.documentos_dias') }} dias</div>
                    </div></div>
                </div>
            @endisset
            @isset($d['qualificacoes'])
                <div class="col-6 col-lg-3">
                    <div class="card shadow h-100"><div class="card-body">
                        <div class="text-muted small">Qualificações com pendência</div>
                        <div class="fs-2 fw-semibold">{{ $pendQualif->count() }}</div>
                        <div class="small"><a href="{{ route('qualificacao.pendentes') }}">Ver pendências</a></div>
                    </div></div>
                </div>
            @endisset
        </div>

        <div class="row g-3">
            @isset($d['documentos'])
                <div class="col-lg-7">
                    <div class="card shadow h-100">
                        <div class="card-header border-0"><h6 class="mb-0">Documentos que pedem atenção</h6></div>
                        <div class="card-body pt-0">
                            @if ($docsVencidos->isEmpty() && $docsAVencer->isEmpty())
                                <p class="text-muted mb-0">Nenhum documento vencido ou a vencer.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>Documento</th><th>De</th><th>Validade</th><th></th></tr></thead>
                                        <tbody>
                                        @foreach ($docsVencidos->take(6)->concat($docsAVencer->take(6)) as $doc)
                                            @php $vencido = $docsVencidos->contains('id', $doc->id); @endphp
                                            <tr>
                                                <td>{{ $doc->tipo }}</td>
                                                <td>{{ $nomeDono($doc->documentavel) }}</td>
                                                <td>{{ $doc->validade->format('d/m/Y') }}</td>
                                                <td><span class="badge text-bg-{{ $vencido ? 'danger' : 'warning' }}">{{ $vencido ? 'Vencido' : 'A vencer' }}</span></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endisset
            @isset($d['por_cargo'])
                <div class="col-lg-5">
                    <div class="card shadow h-100">
                        <div class="card-header border-0"><h6 class="mb-0">Colaboradores por cargo</h6></div>
                        <div class="card-body pt-0">
                            @if ($d['por_cargo']->isEmpty())
                                <p class="text-muted mb-0">Nenhum colaborador com cargo ainda.</p>
                            @else
                                @php $maximo = $d['por_cargo']->max('total'); @endphp
                                @foreach ($d['por_cargo'] as $linha)
                                    <div class="d-flex justify-content-between small"><span>{{ $linha->cargo }}</span><span class="text-semibold">{{ $linha->total }}</span></div>
                                    <div class="progress mb-2" style="height:6px"><div class="progress-bar" style="width: {{ round($linha->total / $maximo * 100) }}%"></div></div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>
            @endisset
        </div>
    </div>
@endsection

@if (! empty($d['indicadores']['custo']['serie']))
    @push('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            if (typeof Chart === 'undefined') return;
            const serie = @json($d['indicadores']['custo']['serie']);
            document.getElementById('tabela-custo')?.classList.add('d-none');
            new Chart(document.getElementById('grafico-custo'), {
                type: 'bar',
                data: { labels: serie.map((p) => p.mes), datasets: [{ label: 'Custo (R$)', data: serie.map((p) => p.total), backgroundColor: '#235A9A' }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        })();
    </script>
    @endpush
@endif

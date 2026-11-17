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

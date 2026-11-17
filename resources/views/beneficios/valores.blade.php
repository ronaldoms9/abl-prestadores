@extends('layouts.modal', ['title' => 'Valores de '.$beneficio->nome])

@section('footer')
    <span></span>
    <button type="button" data-bs-dismiss="modal" class="btn btn-link"><i class="fa fa-times"></i> Fechar</button>
@endsection

@section('content')
    @php $fmt = fn ($v) => 'R$ '.number_format($v, 2, ',', '.'); @endphp
    <p class="text-muted small">{{ $beneficio->tipo_calculo->rotulo() }}. O valor nunca é editado: um valor novo encerra o anterior na véspera da nova data.</p>

    @if ($beneficio->valores->isEmpty())
        <p class="text-muted">Nenhum valor definido.</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>De</th><th>Até</th><th class="text-end">Valor</th></tr></thead>
                <tbody>
                @foreach ($beneficio->valores as $linha)
                    <tr>
                        <td>{{ $linha->vigencia_inicio->format('d/m/Y') }}</td>
                        <td>{{ $linha->vigencia_fim ? $linha->vigencia_fim->copy()->subDay()->format('d/m/Y') : 'atual' }}</td>
                        <td class="text-end">{{ $fmt($linha->valor) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @permission('beneficio-gerir')
        <form id="form-valor" class="row g-2 align-items-end" action="{{ route('beneficio.valor.store', $beneficio->id) }}">
            <div class="col-sm-5">
                <label for="valor" class="small text-semibold">{{ $beneficio->valores->isEmpty() ? 'Valor (R$)' : 'Novo valor (R$)' }}</label>
                <input id="valor" type="number" step="0.01" min="0.01" name="valor" class="form-control form-control-sm" required>
            </div>
            <div class="col-sm-5">
                <label for="vigencia" class="small text-semibold">Vale a partir de</label>
                <input id="vigencia" type="date" name="vigencia" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Definir</button></div>
        </form>
    @endpermission

    @push('scripts')
    <script>
        (() => {
            const form = document.getElementById('form-valor');
            if (!form) return;
            form.addEventListener('submit', (ev) => {
                ev.preventDefault();
                axios.post(form.action, new FormData(form)).then((r) => {
                    showNotify('Sucesso', r.data.message);
                    requestIntoModal(@json(route('beneficio.valores', $beneficio->id)));
                }).catch((e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error'));
            });
        })();
    </script>
    @endpush
@endsection

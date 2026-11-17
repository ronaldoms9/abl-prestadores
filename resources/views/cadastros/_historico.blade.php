{{-- Últimas alterações do registro, da auditoria (Activity), sem os dados bancários. --}}
@if ($pessoa->activities->isEmpty())
    <p class="text-muted mb-0">Sem alterações registradas.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Campos</th></tr></thead>
            <tbody>
            @foreach ($pessoa->activities as $registro)
                <tr>
                    <td class="text-nowrap">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ \App\Support\Auditoria::descreverAutor($registro) }}</td>
                    <td>{{ \App\Support\Auditoria::rotulo($registro->event) }}</td>
                    <td class="text-muted">{{ \App\Support\Auditoria::resumirAlteracoes($registro) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

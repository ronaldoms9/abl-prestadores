<dt class="col-sm-3">Código no UAU</dt>
<dd class="col-sm-9">{{ $registro->uau_codigo ?? '—' }}</dd>

<dt class="col-sm-3">Sincronizado em</dt>
<dd class="col-sm-9">{{ $registro->sincronizado_em?->format('d/m/Y H:i') ?? '—' }}</dd>

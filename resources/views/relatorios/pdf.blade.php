<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $relatorio->titulo() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1b1b1b; }
        h1 { font-size: 15px; margin: 6px 0 2px; color: #0f2a4a; }
        .cab { border-bottom: 2px solid #235A9A; padding-bottom: 6px; margin-bottom: 8px; }
        .cab img { height: 34px; float: right; }
        .meta { color: #555; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #235A9A; color: #fff; text-align: left; padding: 4px; }
        td { border-bottom: 1px solid #ddd; padding: 3px 4px; }
        .d { text-align: right; }
        tfoot td { font-weight: bold; border-top: 2px solid #235A9A; }
    </style>
</head>
<body>
    <div class="cab">
        @if (is_file(public_path('images/logo-prime-azul.svg')))<img src="{{ public_path('images/logo-prime-azul.svg') }}" alt="ABL PRIME">@endif
        <h1>{{ $relatorio->titulo() }}</h1>
        <div class="meta">Emitido em {{ $emissao->format('d/m/Y H:i') }}</div>
        <div class="meta">Filtros: @if ($filtros === []) nenhum @else @foreach ($filtros as $rotulo => $valor){{ $rotulo }}: {{ $valor }}@if (! $loop->last) · @endif @endforeach @endif</div>
    </div>
    <table>
        <thead><tr>@foreach ($resultado->colunas as $c)<th class="{{ in_array($c['tipo'], ['dinheiro', 'numero'], true) ? 'd' : '' }}">{{ $c['rotulo'] }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($resultado->linhas as $l)
            <tr>@foreach ($resultado->colunas as $c)<td class="{{ in_array($c['tipo'], ['dinheiro', 'numero'], true) ? 'd' : '' }}">{{ $l[$c['chave']] ?? '' }}</td>@endforeach</tr>
        @endforeach
        </tbody>
        @if ($resultado->total)
            <tfoot><tr>@foreach ($resultado->colunas as $c)<td class="{{ in_array($c['tipo'], ['dinheiro', 'numero'], true) ? 'd' : '' }}">{{ $resultado->total[$c['chave']] ?? '' }}</td>@endforeach</tr></tfoot>
        @endif
    </table>
</body>
</html>

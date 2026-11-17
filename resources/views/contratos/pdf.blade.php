<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contrato->tipo->rotulo() }} {{ $contrato->numero }}</title>
    <style>
        @page { margin: 2.5cm 2cm; }
        body { font-family: 'DejaVu Serif', serif; font-size: 11pt; line-height: 1.5; color: #000; }
        h1, h2, h3 { text-align: center; }
        p { text-align: justify; margin: 0 0 0.6em; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #000; padding: 4px 6px; }
        .rodape { margin-top: 1.5cm; font-size: 9pt; color: #555; text-align: right; }
    </style>
</head>
<body>
    {!! $contrato->conteudo_renderizado !!}
    <div class="rodape">{{ $contrato->tipo->rotulo() }} nº {{ $contrato->numero }}</div>
</body>
</html>

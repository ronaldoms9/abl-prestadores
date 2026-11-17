@extends('layouts.app', ['title' => $modelo->exists ? 'Editar modelo' : 'Novo modelo'])

@section('content')
@php $abre = '{'.'{'; $fecha = '}'.'}'; @endphp
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $modelo->exists ? 'Editar modelo' : 'Novo modelo' }}
                @if ($modelo->exists)<span class="badge text-bg-secondary ms-2">versão {{ $modelo->versao }}</span>@endif
            </h5>
            <a href="{{ route('contrato-modelo.index') }}" class="btn btn-link btn-sm">Voltar</a>
        </div>
        <div class="card-body">
            @if ($modelo->exists && $modelo->contratos_count > 0)
                <div class="alert alert-info py-2 small">
                    Este modelo já gerou {{ $modelo->contratos_count }} contrato(s). Se você mudar o nome ou o texto, o sistema salva uma <strong>nova versão</strong>
                    e mantém a atual para os contratos já gerados.
                </div>
            @endif

            <form id="form-modelo" onsubmit="return false" action="{{ route('contrato-modelo.store', $modelo->exists ? [$modelo->id] : []) }}">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="small text-semibold">Nome do modelo</label>
                        <input id="nome" type="text" name="nome" class="form-control" value="{{ $modelo->nome }}" required maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label for="tipo" class="small text-semibold">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select" @disabled($modelo->exists && $modelo->contratos_count > 0)>
                            @foreach ($tipos as $tipo)
                                <option value="{{ $tipo->value }}" @selected($modelo->tipo === $tipo)>{{ $tipo->rotulo() }}</option>
                            @endforeach
                        </select>
                        @if ($modelo->exists && $modelo->contratos_count > 0)<input type="hidden" name="tipo" value="{{ $modelo->tipo->value }}">@endif
                    </div>
                    <div class="col-md-3" id="bloco-motivo" {{ $modelo->tipo !== \App\Enums\TipoContrato::Aditivo ? 'hidden' : '' }}>
                        <label for="motivo" class="small text-semibold">Usado para (aditivo)</label>
                        <select id="motivo" name="motivo" class="form-select">
                            <option value="">Qualquer motivo</option>
                            @foreach ($motivos as $motivo)
                                <option value="{{ $motivo->value }}" @selected($modelo->motivo === $motivo)>{{ $motivo->rotulo() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($modelo->exists)
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="ativo" value="0">
                                <input id="ativo" type="checkbox" role="switch" class="form-check-input" name="ativo" value="1" @checked($modelo->ativo)>
                                <label for="ativo" class="form-check-label">Ativo</label>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-lg-9">
                        <div class="btn-toolbar gap-1 mb-2" role="toolbar" aria-label="Formatação">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="bold" title="Negrito"><i class="bi bi-type-bold"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="italic" title="Itálico"><i class="bi bi-type-italic"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="underline" title="Sublinhado"><i class="bi bi-type-underline"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="formatBlock" data-valor="h3" title="Título"><i class="bi bi-type-h3"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="formatBlock" data-valor="p" title="Parágrafo"><i class="bi bi-paragraph"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="insertUnorderedList" title="Lista"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="insertOrderedList" title="Lista numerada"><i class="bi bi-list-ol"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="justifyLeft" title="Alinhar à esquerda"><i class="bi bi-text-left"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="justifyCenter" title="Centralizar"><i class="bi bi-text-center"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-cmd="justifyFull" title="Justificar"><i class="bi bi-justify"></i></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" id="btn-fonte" title="Ver e editar o HTML"><i class="bi bi-code-slash"></i> HTML</button>
                        </div>
                        <div id="editor" class="form-control" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Corpo do modelo"
                             style="min-height: 28rem; height: auto; overflow: auto">{!! $modelo->corpo_html !!}</div>
                        <textarea id="fonte" class="form-control font-monospace" rows="18" hidden aria-label="HTML do modelo"></textarea>
                        <p class="text-muted small mt-1 mb-0">Escreva o texto e clique numa variável ao lado para inseri-la onde está o cursor. Não formate só parte de uma variável (por exemplo, negrito no meio de <code>{{ $abre.'colaborador.nome'.$fecha }}</code>).</p>
                    </div>
                    <div class="col-lg-3">
                        <div class="border rounded p-2" style="max-height: 34rem; overflow: auto">
                            <h6 class="text-semibold small">Variáveis</h6>
                            @foreach ($variaveis as $grupo => $itens)
                                <div class="small text-muted mt-2">{{ $grupo }}</div>
                                @foreach ($itens as $chave => $rotulo)
                                    <button type="button" class="btn btn-link btn-sm text-start p-0 d-block text-decoration-none" data-variavel="{{ $chave }}" title="{{ $abre.$chave.$fecha }}">{{ $rotulo }}</button>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="button" id="btn-salvar" class="btn btn-primary">Salvar</button>
                    <button type="button" id="btn-previa" class="btn btn-outline-primary">Pré-visualizar com dados de exemplo</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow mt-3" id="card-previa" hidden>
        <div class="card-header border-0"><h6 class="mb-0">Pré-visualização</h6></div>
        <div class="card-body">
            <div id="previa-aviso" class="text-danger small mb-2"></div>
            <iframe id="previa" sandbox="" style="width: 100%; min-height: 32rem; border: 1px solid #dee2e6; background: #fff" title="Pré-visualização do modelo"></iframe>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    (() => {
        const form = document.getElementById('form-modelo');
        const editor = document.getElementById('editor');
        const fonte = document.getElementById('fonte');
        let modoFonte = false;

        const html = () => (modoFonte ? fonte.value : editor.innerHTML);
        const falha = (e) => showNotify('Atenção', e.response?.data?.message || 'Ocorreu um erro, informe à administração.', 'error');

        const tipo = document.getElementById('tipo');
        const mostrarMotivo = () => { document.getElementById('bloco-motivo').hidden = tipo.value !== 'aditivo'; };
        tipo.addEventListener('change', mostrarMotivo);

        document.querySelectorAll('[data-cmd]').forEach((b) => b.addEventListener('mousedown', (ev) => {
            ev.preventDefault();
            if (modoFonte) return;
            document.execCommand(b.dataset.cmd, false, b.dataset.valor || null);
        }));

        document.querySelectorAll('[data-variavel]').forEach((b) => b.addEventListener('mousedown', (ev) => {
            ev.preventDefault();
            const texto = @json($abre) + b.dataset.variavel + @json($fecha);
            if (modoFonte) {
                const i = fonte.selectionStart;
                fonte.setRangeText(texto, i, fonte.selectionEnd, 'end');
                fonte.focus();
            } else {
                editor.focus();
                document.execCommand('insertText', false, texto);
            }
        }));

        document.getElementById('btn-fonte').addEventListener('click', () => {
            if (!modoFonte) { fonte.value = editor.innerHTML; } else { editor.innerHTML = fonte.value; }
            modoFonte = !modoFonte;
            editor.hidden = modoFonte;
            fonte.hidden = !modoFonte;
        });

        const dados = () => { const d = new FormData(form); d.set('corpo_html', html()); return d; };

        document.getElementById('btn-salvar').addEventListener('click', () => {
            axios.post(form.action, dados()).then((r) => {
                showNotify('Sucesso', r.data.message);
                setTimeout(() => { location.href = r.data.data.redirect; }, 700);
            }).catch(falha);
        });

        document.getElementById('btn-previa').addEventListener('click', () => {
            axios.post(@json(route('contrato-modelo.previa')), dados()).then((r) => {
                const p = r.data.data;
                document.getElementById('previa').srcdoc = '<body style="font-family: serif; padding: 24px">' + p.html + '</body>';
                document.getElementById('previa-aviso').textContent = p.desconhecidas.length
                    ? 'Variável desconhecida (o modelo não será salvo assim): ' + p.desconhecidas.join(', ') : '';
                document.getElementById('card-previa').hidden = false;
            }).catch(falha);
        });
    })();
</script>
@endpush

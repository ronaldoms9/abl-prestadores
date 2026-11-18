@extends('layouts.app', ['title' => 'Calendário de ausências'])

@section('content')
<div class="container-fluid">
    <div class="card shadow">
        <div class="card-header border-0 d-flex flex-wrap align-items-center gap-2">
            <h5 class="mb-0 me-auto">Calendário de ausências</h5>
            <label for="f-obra" class="small text-semibold mb-0">Obra</label>
            <select id="f-obra" class="form-select form-select-sm" style="width: auto; max-width: 14rem">
                <option value="">Todas</option>
                @foreach ($obras as $o)<option value="{{ $o->id }}">{{ ($o->codigo ? $o->codigo.' · ' : '').$o->descricao }}</option>@endforeach
            </select>
            <label for="f-equipe" class="small text-semibold mb-0">Equipe</label>
            <select id="f-equipe" class="form-select form-select-sm" style="width: auto; max-width: 14rem">
                <option value="">Todas</option>
                @foreach ($equipes as $e)<option value="{{ $e->id }}">{{ $e->nome }}</option>@endforeach
            </select>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3 small mb-3">
                <span><span class="legenda ev-ferias"></span> Férias aprovadas</span>
                <span><span class="legenda ev-ferias-pendente"></span> Férias pendentes</span>
                <span><span class="legenda ev-falta"></span> Falta</span>
                <span><span class="legenda ev-afastamento"></span> Afastamento</span>
                <span><span class="legenda ev-feriado"></span> Feriado</span>
            </div>
            <div id="calendario" data-url="{{ route('ferias.calendario.eventos') }}"></div>
        </div>
    </div>
</div>
@endsection

@push('js')
<style>
    #calendario a { text-decoration: none; color: inherit; }
    #calendario .fc-col-header-cell-cushion { font-weight: 600; }
    .legenda { display: inline-block; width: .9rem; height: .9rem; border-radius: 3px; vertical-align: -2px; margin-right: .25rem; }
    .fc-event.ev-ferias, .legenda.ev-ferias { background: #235A9A; border-color: #235A9A; }
    .fc-event.ev-ferias-pendente, .legenda.ev-ferias-pendente { background: repeating-linear-gradient(45deg, #fff, #fff 4px, #cfe0f3 4px, #cfe0f3 8px); border: 1px dashed #235A9A; color: #0f2a4a; }
    .fc-event.ev-falta, .legenda.ev-falta { background: #b3261e; border-color: #b3261e; }
    .fc-event.ev-afastamento, .legenda.ev-afastamento { background: #7a5d00; border-color: #7a5d00; }
    .fc-event.ev-feriado, .legenda.ev-feriado { background: #c9a227; border-color: #c9a227; color: #1b1b1b; }
</style>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales/pt-br.global.min.js"></script>
<script>
    (() => {
        const el = document.getElementById('calendario');
        const obra = document.getElementById('f-obra');
        const equipe = document.getElementById('f-equipe');
        const calendario = new FullCalendar.Calendar(el, {
            locale: 'pt-br',
            buttonText: { today: 'Hoje', month: 'Mês', list: 'Lista' },
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
            events: (info, ok, falha) => {
                const p = new URLSearchParams({ start: info.startStr.slice(0, 10), end: info.endStr.slice(0, 10) });
                if (obra.value) p.set('obra_id', obra.value);
                if (equipe.value) p.set('equipe_id', equipe.value);
                axios.get(el.dataset.url + '?' + p.toString()).then((r) => ok(r.data)).catch(falha);
            },
        });
        calendario.render();
        [obra, equipe].forEach((s) => s.addEventListener('change', () => calendario.refetchEvents()));
    })();
</script>
@endpush

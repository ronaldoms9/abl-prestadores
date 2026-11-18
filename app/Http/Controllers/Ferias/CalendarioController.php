<?php

namespace App\Http\Controllers\Ferias;

use App\Actions\Ferias\EventosCalendario;
use App\Http\Controllers\Controller;
use App\Models\Equipe;
use App\Models\Obra;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Calendário mensal de férias, faltas, afastamentos e feriados (E4, fatia 4). */
class CalendarioController extends Controller
{
    public function index(Request $request)
    {
        return view('ferias.calendario', [
            'obras' => Obra::query()->ativos()->orderBy('descricao')->get(['id', 'codigo', 'descricao']),
            'equipes' => Equipe::query()->ativos()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function eventos(Request $request, EventosCalendario $eventos)
    {
        $dados = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'obra_id' => ['nullable', 'integer'],
            'equipe_id' => ['nullable', 'integer'],
        ]);

        $inicio = Carbon::parse($dados['start'])->startOfDay();
        // O FullCalendar manda o fim exclusivo; o intervalo é limitado a 100 dias.
        $fim = Carbon::parse($dados['end'])->startOfDay()->subDay();
        abort_if($fim->lt($inicio) || $inicio->diffInDays($fim) > 100, 422, 'Intervalo inválido.');

        return response()->json($eventos->para($request->user(), $inicio, $fim, $dados['obra_id'] ?? null, $dados['equipe_id'] ?? null));
    }
}

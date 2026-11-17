<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Conciliação diária com o UAU (fatia 6). No servidor, o cron roda schedule:run a cada minuto.
        $schedule->command('uau:sincronizar')
            ->dailyAt(config('uau.sincronizacao.horario'))
            ->onOneServer()
            ->withoutOverlapping();

        // Alertas por e-mail (E2, fatias 5 e 7): um resumo por dia, depois da conciliação.
        $schedule->command('alertas:documentos')
            ->dailyAt('06:30')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command('alertas:qualificacoes')
            ->dailyAt('06:45')
            ->onOneServer()
            ->withoutOverlapping();

        // E4, fatia 2: abre os períodos aquisitivos do dia e avisa saldos perto do limite de gozo.
        $schedule->command('ferias:periodos')
            ->dailyAt('05:30')
            ->onOneServer()
            ->withoutOverlapping();

        $schedule->command('alertas:ferias')
            ->dailyAt('07:15')
            ->onOneServer()
            ->withoutOverlapping();

        // E3, fatia 4: contratos vencidos e nos marcos de 30, 15 e 5 dias.
        $schedule->command('alertas:contratos')
            ->dailyAt('07:00')
            ->onOneServer()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

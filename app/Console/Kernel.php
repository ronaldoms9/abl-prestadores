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

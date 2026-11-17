<?php

namespace App\Providers;

use App\Events\BeneficiosDoColaboradorAlterados;
use App\Events\CargoDoColaboradorAlterado;
use App\Events\ReajusteAplicado;
use App\Listeners\GerarAditivoDeAlteracao;
use App\Listeners\GerarAditivosDeReajuste;
use App\Listeners\RegistrarAcesso;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [[RegistrarAcesso::class, 'login']],
        Logout::class => [[RegistrarAcesso::class, 'logout']],
        Failed::class => [[RegistrarAcesso::class, 'falha']],
        ReajusteAplicado::class => [GerarAditivosDeReajuste::class],
        CargoDoColaboradorAlterado::class => [GerarAditivoDeAlteracao::class],
        BeneficiosDoColaboradorAlterados::class => [GerarAditivoDeAlteracao::class],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

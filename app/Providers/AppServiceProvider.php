<?php

namespace App\Providers;

use App\View\Menu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        /**
         * Colunas de autoria e de ciclo de vida, no lugar de timestamps() e
         * softDeletes(). Os autores são opcionais: jobs e seeders gravam sem
         * usuário logado. Par do trait App\Models\Concerns\RegistraAutoria.
         */
        Blueprint::macro('auditoria', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->timestamps();
            $this->softDeletes();
        });

        View::composer('layouts.partials._sidebar', function ($view) {
            $view->with('menu', Menu::fromConfig()->itensVisiveis(auth()->user()));
        });
    }
}

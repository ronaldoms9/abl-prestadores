<?php

namespace App\Providers;

use App\View\Menu;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Política única de senha (Q21), usada em todo ponto em que uma senha é definida.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // D3.6 Morph map explícito para tabelas polimórficas (integracao_logs, activity_log)
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'usuario' => \App\Models\User::class,
            'perfil' => \App\Role::class,
            'empresa' => \App\Models\Empresa::class,
            'obra' => \App\Models\Obra::class,
            'pessoa-fisica' => \App\Models\PessoaFisica::class,
            'pessoa-juridica' => \App\Models\PessoaJuridica::class,
            'importacao-pessoa' => \App\Models\ImportacaoPessoa::class,
        ]);

        // D3.7 Carga preguiçosa barrada fora de produção
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! app()->isProduction());

        View::composer('layouts.partials._sidebar', function ($view) {
            $view->with('menu', Menu::fromConfig()->itensVisiveis(auth()->user()));
        });
    }
}

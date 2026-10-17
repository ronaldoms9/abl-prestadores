<?php

namespace App\Providers;

use App\Integrations\Uau\Sanitizador;
use App\Models\Empresa;
use App\Models\ImportacaoPessoa;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Role;
use App\View\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        $this->app->singleton(Sanitizador::class, fn () => new Sanitizador(config('uau.campos_ocultos', [])));
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

        // Apelidos fixos nas colunas polimórficas (integracao_logs, activity_log), em vez
        // do nome da classe: mover um model de pasta não quebra o histórico. Todo model
        // usado em relação polimórfica precisa estar aqui.
        Relation::enforceMorphMap([
            'usuario' => User::class,
            'perfil' => Role::class,
            'empresa' => Empresa::class,
            'obra' => Obra::class,
            'pessoa-fisica' => PessoaFisica::class,
            'pessoa-juridica' => PessoaJuridica::class,
            'importacao-pessoa' => ImportacaoPessoa::class,
        ]);

        // Fora de produção, relação não carregada com with() quebra em vez de gerar N+1.
        Model::preventLazyLoading(! app()->isProduction());

        View::composer('layouts.partials._sidebar', function ($view) {
            $view->with('menu', Menu::fromConfig()->itensVisiveis(auth()->user()));
        });
    }
}

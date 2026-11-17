<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes(['register' => false]);

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('main');
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', [App\Http\Controllers\HomeController::class, 'index'])->name('dashboard');

    /**
     * Rotas do perfil do usuário
     */
    Route::group(['prefix' => 'perfil'], function(){
        Route::get('/', ['as' => 'profile.edit', 'uses' => 'App\Http\Controllers\ProfileController@edit']);
        Route::put('/', ['as' => 'profile.update', 'uses' => 'App\Http\Controllers\ProfileController@update']);
        Route::put('password', ['as' => 'profile.password', 'uses' => 'App\Http\Controllers\ProfileController@password']);
        Route::patch('photo', ['as' => 'profile.photo', 'uses' => 'App\Http\Controllers\ProfileController@updatePhoto']);
    });


    /**
     * Rotas de usuarios
     */
    Route::group(['prefix' => 'users'], function(){
        Route::get('/', [App\Http\Controllers\Config\UserController::class, 'index'])
            ->name('user.index')->middleware('permission:user-list');
        Route::get('/form/{id?}', [App\Http\Controllers\Config\UserController::class, 'form'])
            ->name('user.form')->middleware('permission:user-create|user-edit');
        Route::post('/store/{id?}', [App\Http\Controllers\Config\UserController::class, 'store'])
            ->name('user.store')->middleware('permission:user-create|user-edit');
        Route::delete('/delete/{id}', [App\Http\Controllers\Config\UserController::class, 'delete'])
            ->name('user.delete')->middleware('permission:user-delete');
    });

    Route::group(['prefix' => 'grupos'], function(){
        Route::get('/', [App\Http\Controllers\Config\PermissoesController::class, 'index'])
            ->name('grupo.index')->middleware('permission:grupo-list');
        Route::get('/form/{id?}', [App\Http\Controllers\Config\PermissoesController::class, 'form'])
            ->name('grupo.form')->middleware('permission:grupo-create|grupo-edit');
        Route::post('/store/{id?}', [App\Http\Controllers\Config\PermissoesController::class, 'store'])
            ->name('grupo.store')->middleware('permission:grupo-create|grupo-edit');
        Route::delete('/delete/{id}', [App\Http\Controllers\Config\PermissoesController::class, 'delete'])
            ->name('grupo.delete')->middleware('permission:grupo-delete');
    });

    /**
     * Telas de consulta (fatia 7), só leitura
     */
    Route::group(['prefix' => 'pessoas/fisicas', 'middleware' => 'permission:pessoa-list'], function(){
        Route::get('/', [App\Http\Controllers\Cadastros\PessoaFisicaController::class, 'index'])->name('pessoa-fisica.index');
        Route::get('/{id}', [App\Http\Controllers\Cadastros\PessoaFisicaController::class, 'show'])
            ->whereNumber('id')->name('pessoa-fisica.show');
    });

    Route::group(['prefix' => 'pessoas/fisicas', 'middleware' => 'permission:pessoa-create|pessoa-edit|colaborador-create|colaborador-edit'], function(){
        Route::get('/buscar', [App\Http\Controllers\Cadastros\PessoaFisicaController::class, 'buscar'])->name('pessoa-fisica.buscar');
    });

    Route::group(['prefix' => 'pessoas/juridicas', 'middleware' => 'permission:colaborador-create|colaborador-edit|pessoa-create|pessoa-edit'], function(){
        Route::get('/buscar', [App\Http\Controllers\Cadastros\PessoaJuridicaController::class, 'buscar'])->name('pessoa-juridica.buscar');
    });

    Route::group(['prefix' => 'pessoas/fisicas', 'middleware' => 'permission:pessoa-create|pessoa-edit'], function(){
        Route::get('/form/{id?}', [App\Http\Controllers\Cadastros\PessoaFisicaController::class, 'form'])
            ->whereNumber('id')->name('pessoa-fisica.form');
        Route::post('/store/{id?}', [App\Http\Controllers\Cadastros\PessoaFisicaController::class, 'store'])
            ->whereNumber('id')->name('pessoa-fisica.store');
    });

    Route::group(['prefix' => 'pessoas/juridicas', 'middleware' => 'permission:pessoa-list'], function(){
        Route::get('/', [App\Http\Controllers\Cadastros\PessoaJuridicaController::class, 'index'])->name('pessoa-juridica.index');
        Route::get('/{id}', [App\Http\Controllers\Cadastros\PessoaJuridicaController::class, 'show'])
            ->whereNumber('id')->name('pessoa-juridica.show');
    });

    Route::group(['prefix' => 'pessoas/juridicas', 'middleware' => 'permission:pessoa-create|pessoa-edit'], function(){
        Route::get('/consultar-cnpj', [App\Http\Controllers\Cadastros\PessoaJuridicaController::class, 'consultarCnpj'])->name('pessoa-juridica.consultar-cnpj');
        Route::get('/form/{id?}', [App\Http\Controllers\Cadastros\PessoaJuridicaController::class, 'form'])
            ->whereNumber('id')->name('pessoa-juridica.form');
        Route::post('/store/{id?}', [App\Http\Controllers\Cadastros\PessoaJuridicaController::class, 'store'])
            ->whereNumber('id')->name('pessoa-juridica.store');
    });

    Route::group(['prefix' => 'documentos'], function(){
        $c = App\Http\Controllers\Cadastros\DocumentoController::class;
        Route::get('/{id}/baixar', [$c, 'baixar'])->whereNumber('id')->name('documento.baixar')->middleware('permission:documento-list');
        Route::delete('/{id}', [$c, 'excluir'])->whereNumber('id')->name('documento.excluir')->middleware('permission:documento-edit');
        Route::post('/{apelido}/{id}', [$c, 'store'])->whereIn('apelido', ['pessoa-fisica', 'pessoa-juridica', 'colaborador'])->whereNumber('id')->name('documento.store')->middleware('permission:documento-edit');
    });

    Route::group(['prefix' => 'tipos-de-documento', 'middleware' => 'permission:documento-tipo-gerir'], function(){
        $c = App\Http\Controllers\Cadastros\DocumentoTipoController::class;
        Route::get('/', [$c, 'index'])->name('documento-tipo.index');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('documento-tipo.form');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('documento-tipo.store');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('documento-tipo.delete');
    });

    Route::group(['prefix' => 'cargos'], function(){
        $c = App\Http\Controllers\Cadastros\CargoController::class;
        Route::get('/', [$c, 'index'])->name('cargo.index')->middleware('permission:cargo-list');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('cargo.form')->middleware('permission:cargo-create|cargo-edit');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('cargo.store')->middleware('permission:cargo-create|cargo-edit');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('cargo.delete')->middleware('permission:cargo-edit');
    });

    Route::group(['prefix' => 'remuneracao', 'middleware' => 'permission:remuneracao-reajustar'], function(){
        $c = App\Http\Controllers\Cadastros\RemuneracaoController::class;
        Route::post('/colaboradores/{id}/definir', [$c, 'definir'])->whereNumber('id')->name('remuneracao.definir');
        Route::post('/colaboradores/{id}/reajustar', [$c, 'reajustar'])->whereNumber('id')->name('remuneracao.reajustar');

        $l = App\Http\Controllers\Cadastros\ReajusteLoteController::class;
        Route::get('/lote', [$l, 'index'])->name('reajuste.lote');
        Route::post('/lote/previa', [$l, 'previa'])->name('reajuste.lote.previa');
        Route::post('/lote/aplicar', [$l, 'aplicar'])->name('reajuste.lote.aplicar');
    });

    Route::group(['prefix' => 'colaboradores'], function(){
        $c = App\Http\Controllers\Cadastros\ColaboradorController::class;
        Route::get('/', [$c, 'index'])->name('colaborador.index')->middleware('permission:colaborador-list');
        Route::get('/sugestoes', [$c, 'sugestoes'])->name('colaborador.sugestoes')->middleware('permission:colaborador-create|colaborador-edit');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('colaborador.form')->middleware('permission:colaborador-create|colaborador-edit');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('colaborador.store')->middleware('permission:colaborador-create|colaborador-edit');
        Route::post('/equipes/{id}/encerrar', [$c, 'equipeEncerrar'])->whereNumber('id')->name('colaborador.equipe.encerrar')->middleware('permission:colaborador-edit');
        Route::post('/{id}/equipes', [$c, 'equipeStore'])->whereNumber('id')->name('colaborador.equipe.store')->middleware('permission:colaborador-edit');
        Route::get('/{id}', [$c, 'show'])->whereNumber('id')->name('colaborador.show')->middleware('permission:colaborador-list');
    });

    Route::group(['prefix' => 'equipes'], function(){
        $c = App\Http\Controllers\Cadastros\EquipeController::class;
        Route::get('/', [$c, 'index'])->name('equipe.index')->middleware('permission:equipe-list');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('equipe.form')->middleware('permission:equipe-create|equipe-edit');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('equipe.store')->middleware('permission:equipe-create|equipe-edit');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('equipe.delete')->middleware('permission:equipe-edit');
        Route::post('/gestores/{id}/encerrar', [$c, 'gestorEncerrar'])->whereNumber('id')->name('equipe.gestor.encerrar')->middleware('permission:equipe-edit');
        Route::post('/{id}/gestores', [$c, 'gestorStore'])->whereNumber('id')->name('equipe.gestor.store')->middleware('permission:equipe-edit');
        Route::get('/{id}', [$c, 'show'])->whereNumber('id')->name('equipe.show')->middleware('permission:equipe-list');
    });

    Route::group(['prefix' => 'empresas', 'middleware' => 'permission:empresa-list'], function(){
        Route::get('/', [App\Http\Controllers\Cadastros\EmpresaController::class, 'index'])->name('empresa.index');
        Route::get('/{id}', [App\Http\Controllers\Cadastros\EmpresaController::class, 'show'])
            ->whereNumber('id')->name('empresa.show');
    });

    Route::group(['prefix' => 'obras', 'middleware' => 'permission:obra-list'], function(){
        Route::get('/', [App\Http\Controllers\Cadastros\ObraController::class, 'index'])->name('obra.index');
        Route::get('/{id}', [App\Http\Controllers\Cadastros\ObraController::class, 'show'])
            ->whereNumber('id')->name('obra.show');
    });

    Route::group(['prefix' => 'pessoas/planilhas', 'middleware' => 'permission:pessoa-importar'], function(){
        Route::get('/', [App\Http\Controllers\Pessoas\ImportacaoPessoaController::class, 'index'])->name('importacao-pessoa.index');
        Route::get('/modelo', [App\Http\Controllers\Pessoas\ImportacaoPessoaController::class, 'modelo'])->name('importacao-pessoa.modelo');
        Route::post('/', [App\Http\Controllers\Pessoas\ImportacaoPessoaController::class, 'store'])->name('importacao-pessoa.store');
        Route::get('/{id}', [App\Http\Controllers\Pessoas\ImportacaoPessoaController::class, 'show'])
            ->whereNumber('id')->name('importacao-pessoa.show');
    });

    Route::group(['prefix' => 'integracoes', 'middleware' => 'permission:integracao-list'], function(){
        Route::get('/', [App\Http\Controllers\Integracoes\IntegracaoController::class, 'index'])->name('integracao.index');
        Route::get('/{id}', [App\Http\Controllers\Integracoes\IntegracaoController::class, 'show'])
            ->whereNumber('id')->name('integracao.show');
    });
    Route::post('/integracoes/sincronizar', [App\Http\Controllers\Integracoes\IntegracaoController::class, 'sincronizar'])
        ->name('integracao.sincronizar')->middleware('permission:integracao-sincronizar');
    Route::post('/integracoes/{id}/reprocessar', [App\Http\Controllers\Integracoes\IntegracaoController::class, 'reprocessar'])
        ->whereNumber('id')->name('integracao.reprocessar')->middleware('permission:integracao-reenviar');

    Route::group(['prefix' => 'auditoria', 'middleware' => 'permission:auditoria-list'], function(){
        Route::get('/', [App\Http\Controllers\Config\AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/{id}', [App\Http\Controllers\Config\AuditoriaController::class, 'show'])
            ->whereNumber('id')->name('auditoria.show');
    });

});


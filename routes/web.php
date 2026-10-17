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
    Route::post('/integracoes/{id}/reprocessar', [App\Http\Controllers\Integracoes\IntegracaoController::class, 'reprocessar'])
        ->whereNumber('id')->name('integracao.reprocessar')->middleware('permission:integracao-reenviar');

    Route::group(['prefix' => 'auditoria', 'middleware' => 'permission:auditoria-list'], function(){
        Route::get('/', [App\Http\Controllers\Config\AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('/{id}', [App\Http\Controllers\Config\AuditoriaController::class, 'show'])
            ->whereNumber('id')->name('auditoria.show');
    });

});


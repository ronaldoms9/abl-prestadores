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

    Route::group(['prefix' => 'qualificacoes'], function(){
        $t = App\Http\Controllers\Cadastros\QualificacaoTipoController::class;
        $m = App\Http\Controllers\Cadastros\MatrizQualificacoesController::class;
        $q = App\Http\Controllers\Cadastros\QualificacaoController::class;

        Route::get('/pendentes', [$q, 'pendentes'])->name('qualificacao.pendentes')->middleware('permission:qualificacao-list');
        Route::get('/{id}/certificado', [$q, 'certificado'])->whereNumber('id')->name('qualificacao.certificado')->middleware('permission:qualificacao-list');
        Route::post('/colaboradores/{id}', [$q, 'registrar'])->whereNumber('id')->name('qualificacao.registrar')->middleware('permission:qualificacao-edit');
        Route::delete('/{id}', [$q, 'excluir'])->whereNumber('id')->name('qualificacao.excluir')->middleware('permission:qualificacao-edit');

        Route::group(['middleware' => 'permission:qualificacao-tipo-gerir'], function() use ($t, $m){
            Route::get('/tipos', [$t, 'index'])->name('qualificacao-tipo.index');
            Route::get('/tipos/form/{id?}', [$t, 'form'])->whereNumber('id')->name('qualificacao-tipo.form');
            Route::post('/tipos/store/{id?}', [$t, 'store'])->whereNumber('id')->name('qualificacao-tipo.store');
            Route::delete('/tipos/delete/{id}', [$t, 'delete'])->whereNumber('id')->name('qualificacao-tipo.delete');
            Route::get('/matriz', [$m, 'index'])->name('qualificacao.matriz');
            Route::post('/matriz', [$m, 'salvar'])->name('qualificacao.matriz.salvar');
        });
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

    Route::group(['prefix' => 'desligamento', 'middleware' => 'permission:colaborador-desligar'], function(){
        $c = App\Http\Controllers\Cadastros\DesligamentoController::class;
        Route::get('/colaboradores/{id}', [$c, 'form'])->whereNumber('id')->name('desligamento.form');
        Route::get('/colaboradores/{id}/previa', [$c, 'previa'])->whereNumber('id')->name('desligamento.previa');
        Route::post('/colaboradores/{id}', [$c, 'store'])->whereNumber('id')->name('desligamento.store');
    });

    Route::group(['prefix' => 'admissao', 'middleware' => 'permission:admissao-gerir'], function(){
        $a = App\Http\Controllers\Cadastros\AdmissaoController::class;
        $i = App\Http\Controllers\Cadastros\AdmissaoChecklistItemController::class;
        Route::post('/colaboradores/{id}/iniciar', [$a, 'iniciar'])->whereNumber('id')->name('admissao.iniciar');
        Route::post('/colaboradores/{id}/concluir', [$a, 'concluir'])->whereNumber('id')->name('admissao.concluir');
        Route::post('/itens-do-colaborador/{id}', [$a, 'marcar'])->whereNumber('id')->name('admissao.marcar');

        Route::get('/itens', [$i, 'index'])->name('admissao-item.index');
        Route::get('/itens/form/{id?}', [$i, 'form'])->whereNumber('id')->name('admissao-item.form');
        Route::post('/itens/store/{id?}', [$i, 'store'])->whereNumber('id')->name('admissao-item.store');
        Route::delete('/itens/delete/{id}', [$i, 'delete'])->whereNumber('id')->name('admissao-item.delete');
    });

    Route::group(['prefix' => 'contratos'], function(){
        $c = App\Http\Controllers\Contratos\ContratoController::class;
        $a = App\Http\Controllers\Contratos\AditivoPendenteController::class;
        Route::get('/aditivos-pendentes', [$a, 'index'])->name('aditivo.pendentes')->middleware('permission:contrato-gerar');
        Route::post('/aditivos-pendentes/gerar', [$a, 'gerar'])->name('aditivo.pendentes.gerar')->middleware('permission:contrato-gerar');
        Route::post('/colaboradores/{id}', [$c, 'store'])->whereNumber('id')->name('contrato.store')->middleware('permission:contrato-gerar');
        Route::get('/{id}/previa', [$c, 'previa'])->whereNumber('id')->name('contrato.previa')->middleware('permission:contrato-list');
        Route::post('/{id}/gerar', [$c, 'gerar'])->whereNumber('id')->name('contrato.gerar')->middleware('permission:contrato-gerar');
        Route::delete('/{id}', [$c, 'cancelar'])->whereNumber('id')->name('contrato.cancelar')->middleware('permission:contrato-gerar');
        Route::post('/{id}/renovar', [$c, 'renovar'])->whereNumber('id')->name('contrato.renovar')->middleware('permission:contrato-gerar');
        Route::post('/{id}/pdf', [$c, 'regerarPdf'])->whereNumber('id')->name('contrato.pdf.regerar')->middleware('permission:contrato-gerar');
        Route::get('/{id}/pdf', [$c, 'pdf'])->whereNumber('id')->name('contrato.pdf')->middleware('permission:contrato-list');
        Route::post('/{id}/assinado', [$c, 'assinado'])->whereNumber('id')->name('contrato.assinado')->middleware('permission:contrato-assinar');
        Route::get('/{id}/assinado', [$c, 'baixarAssinado'])->whereNumber('id')->name('contrato.assinado.baixar')->middleware('permission:contrato-list');
    });

    Route::group(['prefix' => 'contratos/modelos', 'middleware' => 'permission:contrato-modelo-gerir'], function(){
        $c = App\Http\Controllers\Contratos\ModeloController::class;
        Route::get('/', [$c, 'index'])->name('contrato-modelo.index');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('contrato-modelo.form');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('contrato-modelo.store');
        Route::post('/previa', [$c, 'previa'])->name('contrato-modelo.previa');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('contrato-modelo.delete');
    });

    Route::group(['prefix' => 'beneficios'], function(){
        $c = App\Http\Controllers\Cadastros\BeneficioController::class;
        $r = App\Http\Controllers\Cadastros\ReajusteBeneficioController::class;
        Route::get('/', [$c, 'index'])->name('beneficio.index')->middleware('permission:beneficio-list');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('beneficio.form')->middleware('permission:beneficio-gerir');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('beneficio.store')->middleware('permission:beneficio-gerir');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('beneficio.delete')->middleware('permission:beneficio-gerir');
        Route::get('/{id}/valores', [$c, 'valores'])->whereNumber('id')->name('beneficio.valores')->middleware('permission:beneficio-list');
        Route::post('/{id}/valores', [$c, 'valorStore'])->whereNumber('id')->name('beneficio.valor.store')->middleware('permission:beneficio-gerir');

        Route::group(['prefix' => 'reajuste', 'middleware' => 'permission:beneficio-gerir'], function() use ($r){
            Route::get('/', [$r, 'index'])->name('reajuste.beneficios');
            Route::post('/previa', [$r, 'previa'])->name('reajuste.beneficios.previa');
            Route::post('/aplicar', [$r, 'aplicar'])->name('reajuste.beneficios.aplicar');
        });
    });

    Route::group(['prefix' => 'ferias'], function(){
        $c = App\Http\Controllers\Ferias\FeriasController::class;
        $n = App\Http\Controllers\Ferias\FeriasNivelController::class;
        $cal = App\Http\Controllers\Ferias\CalendarioController::class;
        Route::get('/calendario', [$cal, 'index'])->name('ferias.calendario')->middleware('permission:ferias-list');
        Route::get('/calendario/eventos', [$cal, 'eventos'])->name('ferias.calendario.eventos')->middleware('permission:ferias-list');
        Route::get('/', [$c, 'index'])->name('ferias.index')->middleware('permission:ferias-list|ferias-aprovar-rh|ferias-aprovar-qualquer');
        Route::post('/colaboradores/{id}/solicitar', [$c, 'solicitar'])->whereNumber('id')->name('ferias.solicitar')->middleware('permission:ferias-solicitar');
        Route::get('/{id}/decidir', [$c, 'decidirForm'])->whereNumber('id')->name('ferias.decidir.form')->middleware('permission:ferias-list|ferias-aprovar-rh|ferias-aprovar-qualquer');
        Route::post('/{id}/decidir', [$c, 'decidir'])->whereNumber('id')->name('ferias.decidir')->middleware('permission:ferias-list|ferias-aprovar-rh|ferias-aprovar-qualquer');
        Route::post('/{id}/cancelar', [$c, 'cancelar'])->whereNumber('id')->name('ferias.cancelar')->middleware('permission:ferias-solicitar');

        Route::group(['prefix' => 'niveis', 'middleware' => 'permission:ferias-niveis-configurar'], function() use ($n){
            Route::get('/', [$n, 'index'])->name('ferias-nivel.index');
            Route::get('/form/{id?}', [$n, 'form'])->whereNumber('id')->name('ferias-nivel.form');
            Route::post('/store/{id?}', [$n, 'store'])->whereNumber('id')->name('ferias-nivel.store');
            Route::delete('/delete/{id}', [$n, 'delete'])->whereNumber('id')->name('ferias-nivel.delete');
        });
    });

    Route::group(['prefix' => 'relatorios'], function(){
        $c = App\Http\Controllers\Relatorios\RelatorioController::class;
        Route::get('/', [$c, 'index'])->name('relatorio.index')->middleware('permission:fechamento-list|ferias-list|qualificacao-list|contrato-list|nota-list');
        Route::get('/{slug}', [$c, 'show'])->name('relatorio.show')->middleware('permission:fechamento-list|ferias-list|qualificacao-list|contrato-list|nota-list');
        Route::get('/{slug}/exportar/{formato}', [$c, 'exportar'])->whereIn('formato', ['xlsx', 'pdf'])->name('relatorio.exportar')->middleware('permission:fechamento-list|ferias-list|qualificacao-list|contrato-list|nota-list');
    });

    Route::group(['prefix' => 'portal', 'middleware' => 'permission:portal-acessar'], function(){
        $c = App\Http\Controllers\Portal\PortalController::class;
        Route::get('/', [$c, 'index'])->name('portal.index');
        Route::get('/ferias', [$c, 'ferias'])->name('portal.ferias');
        Route::post('/ferias', [$c, 'feriasSolicitar'])->name('portal.ferias.solicitar');
        Route::post('/ferias/{id}/cancelar', [$c, 'feriasCancelar'])->whereNumber('id')->name('portal.ferias.cancelar');
        Route::get('/notas', [$c, 'notas'])->name('portal.notas');
        Route::post('/notas/previa', [$c, 'notaPrevia'])->name('portal.nota.previa');
        Route::get('/notas/{fechamento}/enviar', [$c, 'notaForm'])->whereNumber('fechamento')->name('portal.nota.form');
        Route::post('/notas/{fechamento}/enviar', [$c, 'notaEnviar'])->whereNumber('fechamento')->name('portal.nota.enviar');
        Route::get('/notas/{id}/arquivo/{tipo}', [$c, 'notaArquivo'])->whereNumber('id')->whereIn('tipo', ['pdf', 'xml'])->name('portal.nota.arquivo');
    });

    Route::group(['prefix' => 'notas'], function(){
        $c = App\Http\Controllers\Notas\NotaController::class;
        Route::get('/', [$c, 'index'])->name('nota.index')->middleware('permission:nota-list');
        Route::get('/zip/{arquivo}', [$c, 'zipBaixar'])->name('nota.zip.baixar')->middleware(['signed', 'permission:nota-list']);
        Route::post('/competencias/{competencia}/zip', [$c, 'zip'])->whereNumber('competencia')->name('nota.zip')->middleware('permission:nota-list');
        Route::post('/previa', [$c, 'previa'])->name('nota.previa')->middleware('permission:nota-enviar');
        Route::get('/enviar/{fechamento}', [$c, 'enviarForm'])->whereNumber('fechamento')->name('nota.enviar.form')->middleware('permission:nota-enviar');
        Route::post('/enviar/{fechamento}', [$c, 'enviar'])->whereNumber('fechamento')->name('nota.enviar')->middleware('permission:nota-enviar');
        Route::get('/{id}', [$c, 'show'])->whereNumber('id')->name('nota.show')->middleware('permission:nota-list');
        Route::get('/{id}/arquivo/{tipo}', [$c, 'arquivo'])->whereNumber('id')->whereIn('tipo', ['pdf', 'xml'])->name('nota.arquivo')->middleware('permission:nota-list');
        Route::get('/{id}/decidir', [$c, 'decidirForm'])->whereNumber('id')->name('nota.decidir.form')->middleware('permission:nota-decidir');
        Route::post('/{id}/decidir', [$c, 'decidir'])->whereNumber('id')->name('nota.decidir')->middleware('permission:nota-decidir');
    });

    Route::group(['prefix' => 'fechamento'], function(){
        $c = App\Http\Controllers\Fechamento\FechamentoController::class;
        Route::get('/', [$c, 'index'])->name('fechamento.index')->middleware('permission:fechamento-list');
        Route::post('/abrir', [$c, 'abrir'])->name('fechamento.abrir')->middleware('permission:fechamento-conferir');
        Route::get('/competencias/{id}', [$c, 'show'])->whereNumber('id')->name('fechamento.show')->middleware('permission:fechamento-list');
        Route::post('/competencias/{id}/recalcular-todos', [$c, 'recalcularTodos'])->whereNumber('id')->name('fechamento.recalcular-todos')->middleware('permission:fechamento-conferir');
        Route::post('/competencias/{id}/aprovar', [$c, 'aprovar'])->whereNumber('id')->name('fechamento.aprovar')->middleware('permission:fechamento-aprovar');
        Route::post('/competencias/{id}/reabrir', [$c, 'reabrir'])->whereNumber('id')->name('fechamento.reabrir')->middleware('permission:fechamento-reabrir');
        Route::get('/{id}', [$c, 'detalhe'])->whereNumber('id')->name('fechamento.detalhe')->middleware('permission:fechamento-list');
        Route::post('/{id}/recalcular', [$c, 'recalcular'])->whereNumber('id')->name('fechamento.recalcular')->middleware('permission:fechamento-conferir');
        Route::post('/{id}/ajustes', [$c, 'ajusteStore'])->whereNumber('id')->name('fechamento.ajuste.store')->middleware('permission:fechamento-conferir');
        Route::delete('/ajustes/{id}', [$c, 'ajusteDelete'])->whereNumber('id')->name('fechamento.ajuste.delete')->middleware('permission:fechamento-conferir');
    });

    Route::group(['prefix' => 'feriados'], function(){
        $c = App\Http\Controllers\Cadastros\FeriadoController::class;
        Route::get('/', [$c, 'index'])->name('feriado.index')->middleware('permission:feriado-list');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('feriado.form')->middleware('permission:feriado-gerir');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('feriado.store')->middleware('permission:feriado-gerir');
        Route::post('/importar', [$c, 'importar'])->name('feriado.importar')->middleware('permission:feriado-gerir');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('feriado.delete')->middleware('permission:feriado-gerir');
    });

    Route::group(['prefix' => 'ocorrencias'], function(){
        $c = App\Http\Controllers\Cadastros\OcorrenciaController::class;
        Route::get('/', [$c, 'index'])->name('ocorrencia.index')->middleware('permission:ocorrencia-list');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('ocorrencia.form')->middleware('permission:ocorrencia-gerir');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('ocorrencia.store')->middleware('permission:ocorrencia-gerir');
        Route::delete('/delete/{id}', [$c, 'delete'])->whereNumber('id')->name('ocorrencia.delete')->middleware('permission:ocorrencia-gerir');
    });

    Route::group(['prefix' => 'colaboradores'], function(){
        $c = App\Http\Controllers\Cadastros\ColaboradorController::class;
        Route::get('/', [$c, 'index'])->name('colaborador.index')->middleware('permission:colaborador-list');
        Route::get('/sugestoes', [$c, 'sugestoes'])->name('colaborador.sugestoes')->middleware('permission:colaborador-create|colaborador-edit');
        Route::get('/form/{id?}', [$c, 'form'])->whereNumber('id')->name('colaborador.form')->middleware('permission:colaborador-create|colaborador-edit');
        Route::post('/store/{id?}', [$c, 'store'])->whereNumber('id')->name('colaborador.store')->middleware('permission:colaborador-create|colaborador-edit');
        Route::post('/equipes/{id}/encerrar', [$c, 'equipeEncerrar'])->whereNumber('id')->name('colaborador.equipe.encerrar')->middleware('permission:colaborador-edit');
        $b = App\Http\Controllers\Cadastros\ColaboradorBeneficioController::class;
        Route::post('/{id}/beneficios', [$b, 'store'])->whereNumber('id')->name('colaborador.beneficio.store')->middleware('permission:colaborador-beneficio-edit');
        Route::post('/beneficios/{id}/encerrar', [$b, 'encerrar'])->whereNumber('id')->name('colaborador.beneficio.encerrar')->middleware('permission:colaborador-beneficio-edit');
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


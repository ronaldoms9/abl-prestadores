<?php

namespace Tests\Concerns;

use App\Integrations\Uau\FakeUauClient;
use App\Integrations\Uau\RegistroIntegracao;
use App\Integrations\Uau\UauGateway;
use Illuminate\Support\Facades\File;

/**
 * UAU simulado com dados alterados por teste, sem tocar nos dados versionados.
 *
 * simularUau() copia resources/fixtures/uau para uma pasta temporária, aplica
 * as sobrescritas e liga um FakeUauClient apontando para ela. Chamar de novo
 * troca o "UAU" no meio do teste (ex.: uma empresa que some da lista).
 */
trait UauSimulado
{
    /**
     * @param  array<string, mixed>  $sobrescritas  Caminho relativo => conteúdo (vira JSON) ou null (apaga o arquivo).
     *                                              Ex.: ['empresas-ativas.json' => [...], 'pessoas/52998224725.json' => null].
     */
    protected function simularUau(array $sobrescritas = []): FakeUauClient
    {
        $diretorio = sys_get_temp_dir().'/uau-simulado-'.bin2hex(random_bytes(6));
        File::copyDirectory(resource_path('fixtures/uau'), $diretorio);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($diretorio));

        foreach ($sobrescritas as $arquivo => $conteudo) {
            $caminho = "{$diretorio}/{$arquivo}";

            if ($conteudo === null) {
                File::delete($caminho);

                continue;
            }

            File::ensureDirectoryExists(dirname($caminho));
            File::put($caminho, json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $uau = new FakeUauClient($this->app->make(RegistroIntegracao::class), $diretorio);
        $this->app->instance(UauGateway::class, $uau);
        $this->app->instance(FakeUauClient::class, $uau);

        return $uau;
    }

    /** Conteúdo de um arquivo dos dados simulados versionados, para alterar e sobrescrever. */
    protected function dadosUau(string $arquivo): mixed
    {
        return json_decode(File::get(resource_path("fixtures/uau/{$arquivo}")), true, flags: JSON_THROW_ON_ERROR);
    }
}

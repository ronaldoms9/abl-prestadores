<?php

namespace Tests\Feature\Tenancy;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Modelo de apoio: exercita a trait sem depender de nenhuma tabela de negócio. */
class RegistroDeTeste extends Model
{
    use BelongsToTenant;

    protected $table = 'registros_de_teste';

    protected $fillable = ['tenant_id', 'titulo'];
}

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('registros_de_teste', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('titulo');
            $table->timestamps();
        });
    }

    public function test_um_tenant_nao_enxerga_registros_de_outro(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        RegistroDeTeste::create(['tenant_id' => $a->id, 'titulo' => 'do tenant A']);
        RegistroDeTeste::create(['tenant_id' => $b->id, 'titulo' => 'do tenant B']);

        $contexto = app(TenantContext::class);

        $contexto->set($a);
        $this->assertSame(['do tenant A'], RegistroDeTeste::pluck('titulo')->all());

        $contexto->set($b);
        $this->assertSame(['do tenant B'], RegistroDeTeste::pluck('titulo')->all());

        $contexto->set(null);
        $this->assertCount(2, RegistroDeTeste::all());
    }

    public function test_tenant_id_e_preenchido_a_partir_do_contexto(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);

        $registro = RegistroDeTeste::create(['titulo' => 'sem tenant explícito']);

        $this->assertSame($tenant->id, $registro->tenant_id);
    }

    public function test_escopo_pode_ser_removido_conscientemente(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        RegistroDeTeste::create(['tenant_id' => $a->id, 'titulo' => 'A']);
        RegistroDeTeste::create(['tenant_id' => $b->id, 'titulo' => 'B']);

        app(TenantContext::class)->set($a);

        $this->assertCount(2, RegistroDeTeste::semEscopoDeTenant()->get());
    }

    public function test_usuario_sem_tenant_e_do_time_interno(): void
    {
        $interno = User::factory()->create(['tenant_id' => null]);
        $doCliente = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->assertTrue($interno->ehTimeInterno());
        $this->assertFalse($doCliente->ehTimeInterno());
    }

    public function test_tenant_vencido_nao_esta_ativo(): void
    {
        $this->assertTrue(Tenant::factory()->create()->estaAtivo());
        $this->assertFalse(Tenant::factory()->vencido()->create()->estaAtivo());
    }
}

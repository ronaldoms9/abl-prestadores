<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_preenche_o_contexto_a_partir_do_usuario_logado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Route::middleware(['web'])->get('/_teste/tenant', fn () => [
            'tenant_id' => app(TenantContext::class)->id(),
        ]);

        $this->actingAs($user)
            ->get('/_teste/tenant')
            ->assertOk()
            ->assertJson(['tenant_id' => $tenant->id]);
    }

    public function test_visitante_nao_define_tenant(): void
    {
        Route::middleware([ResolveTenant::class])->get('/_teste/tenant-anonimo', fn () => [
            'tenant_id' => app(TenantContext::class)->id(),
        ]);

        $this->get('/_teste/tenant-anonimo')
            ->assertOk()
            ->assertJson(['tenant_id' => null]);
    }
}

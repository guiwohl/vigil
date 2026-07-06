<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_provisions_a_tenant_and_links_the_user(): void
    {
        $this->post('/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertNotNull($user->tenant_id);

        $tenant = Tenant::findOrFail($user->tenant_id);
        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan);
        $this->assertSame(3, $tenant->monitor_limit);
        $this->assertNotEmpty($tenant->slug);
    }
}

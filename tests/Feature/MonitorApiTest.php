<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_monitor_for_their_tenant(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/monitors', [
            'name' => 'Example',
            'url' => 'https://example.com',
            'interval_seconds' => 60,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('monitors', [
            'name' => 'Example',
            'tenant_id' => $user->tenant_id,
            'status' => Monitor::UNKNOWN,
        ]);
    }

    public function test_creating_past_the_monitor_limit_is_rejected_with_402(): void
    {
        $tenant = Tenant::factory()->create(['monitor_limit' => 3]);
        $user = User::factory()->for($tenant)->create();
        Monitor::factory()->count(3)->for($tenant)->create();

        $response = $this->actingAs($user)->post('/monitors', [
            'name' => 'Over limit',
            'url' => 'https://example.com',
        ]);

        $response->assertStatus(402);
        $this->assertSame(3, $tenant->monitors()->count());
    }

    public function test_a_tenant_cannot_touch_another_tenants_monitor(): void
    {
        $victim = Monitor::factory()->create();
        $attacker = User::factory()->create();

        $this->assertNotSame($victim->tenant_id, $attacker->tenant_id);

        $this->actingAs($attacker)
            ->delete("/monitors/{$victim->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('monitors', ['id' => $victim->id]);
    }

    public function test_a_user_can_delete_their_own_monitor(): void
    {
        $user = User::factory()->create();
        $monitor = Monitor::factory()->for($user->tenant)->create();

        $this->actingAs($user)
            ->delete("/monitors/{$monitor->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }
}

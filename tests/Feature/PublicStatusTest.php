<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_status_page_needs_no_auth_and_reports_operational(): void
    {
        $tenant = Tenant::factory()->create();
        Monitor::factory()->for($tenant)->create(['status' => Monitor::UP]);

        $response = $this->getJson("/status/{$tenant->slug}/data");

        $response->assertOk();
        $response->assertJsonPath('slug', $tenant->slug);
        $response->assertJsonPath('overall', 'operational');
        $response->assertJsonCount(1, 'monitors');
    }

    public function test_a_down_monitor_makes_the_page_report_down(): void
    {
        $tenant = Tenant::factory()->create();
        Monitor::factory()->for($tenant)->create(['status' => Monitor::UP]);
        Monitor::factory()->for($tenant)->create(['status' => Monitor::DOWN]);

        $this->getJson("/status/{$tenant->slug}/data")
            ->assertOk()
            ->assertJsonPath('overall', 'down');
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/status/does-not-exist/data')->assertNotFound();
    }

    public function test_visitors_can_subscribe_to_a_status_page(): void
    {
        $tenant = Tenant::factory()->create();

        $this->postJson("/status/{$tenant->slug}/subscribe", ['email' => 'sub@example.com'])
            ->assertCreated()
            ->assertExactJson(['ok' => true]);

        $this->assertDatabaseHas('subscribers', [
            'tenant_id' => $tenant->id,
            'email' => 'sub@example.com',
        ]);
    }
}

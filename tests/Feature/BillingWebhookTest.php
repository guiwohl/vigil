<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.webhook.secret' => $this->secret]);
        config(['services.stripe.paid_monitor_limit' => 50]);
        config(['services.stripe.default_monitor_limit' => 3]);
    }

    private function signed(array $payload): array
    {
        $body = json_encode($payload);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $this->secret);

        return [$body, "t={$timestamp},v1={$signature}"];
    }

    public function test_a_properly_signed_webhook_upgrades_then_downgrades_the_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_FREE,
            'monitor_limit' => 3,
            'stripe_id' => 'cus_123',
        ]);

        [$body, $sig] = $this->signed([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'metadata' => ['tenant_id' => (string) $tenant->id],
                'subscription' => 'sub_123',
            ]],
        ]);

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $tenant->refresh();
        $this->assertSame(Tenant::PLAN_PRO, $tenant->plan);
        $this->assertSame(50, $tenant->monitor_limit);
        $this->assertSame('sub_123', $tenant->stripe_subscription_id);

        [$body, $sig] = $this->signed([
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => ['id' => 'sub_123', 'customer' => 'cus_123']],
        ]);

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        $tenant->refresh();
        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan);
        $this->assertSame(3, $tenant->monitor_limit);
        $this->assertNull($tenant->stripe_subscription_id);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $tenant = Tenant::factory()->create(['plan' => Tenant::PLAN_FREE]);

        $body = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'metadata' => ['tenant_id' => (string) $tenant->id],
                'subscription' => 'sub_evil',
            ]],
        ]);

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef',
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertForbidden();

        $tenant->refresh();
        $this->assertSame(Tenant::PLAN_FREE, $tenant->plan);
    }
}

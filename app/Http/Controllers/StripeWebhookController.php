<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stripe webhook. Signature verification is enforced upstream by Cashier's
 * VerifyWebhookSignature middleware (config: cashier.webhook.secret) — a tampered
 * or missing signature is rejected before this controller runs. Mirrors the
 * reference billing/service.py: flip the tenant plan on subscription lifecycle.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $object = $request->input('data.object', []);

        match ($type) {
            'checkout.session.completed' => $this->upgrade($object),
            'customer.subscription.deleted' => $this->downgrade($object),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    private function upgrade(array $object): void
    {
        $tenantId = $object['metadata']['tenant_id'] ?? null;

        if ($tenantId === null) {
            return;
        }

        $tenant = Tenant::find((int) $tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->update([
            'plan' => Tenant::PLAN_PRO,
            'monitor_limit' => (int) config('services.stripe.paid_monitor_limit', 50),
            'stripe_subscription_id' => $object['subscription'] ?? null,
        ]);
    }

    private function downgrade(array $object): void
    {
        $tenant = Tenant::query()
            ->when($object['id'] ?? null, fn ($q, $id) => $q->orWhere('stripe_subscription_id', $id))
            ->when($object['customer'] ?? null, fn ($q, $id) => $q->orWhere('stripe_id', $id))
            ->first();

        if ($tenant === null) {
            return;
        }

        $tenant->update([
            'plan' => Tenant::PLAN_FREE,
            'monitor_limit' => (int) config('services.stripe.default_monitor_limit', 3),
            'stripe_subscription_id' => null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function show(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('billing', [
            'billing' => [
                'plan' => $tenant->plan,
                'monitor_limit' => $tenant->monitor_limit,
                'configured' => filled(config('cashier.secret')),
            ],
        ]);
    }

    public function checkout(Request $request)
    {
        if (blank(config('cashier.secret')) || blank(config('services.stripe.price_id'))) {
            abort(503, 'Stripe is not configured. Add your test keys to .env to enable checkout.');
        }

        $tenant = $request->user()->tenant;

        return $tenant->checkout([config('services.stripe.price_id') => 1], [
            'success_url' => route('billing').'?checkout=success',
            'cancel_url' => route('billing').'?checkout=cancel',
            'metadata' => ['tenant_id' => $tenant->id],
        ])->redirect();
    }
}

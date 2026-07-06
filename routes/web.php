<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('monitors', [MonitorController::class, 'store'])->name('monitors.store');
    Route::patch('monitors/{monitor}', [MonitorController::class, 'update'])->name('monitors.update');
    Route::delete('monitors/{monitor}', [MonitorController::class, 'destroy'])->name('monitors.destroy');

    Route::get('billing', [BillingController::class, 'show'])->name('billing');
    Route::post('billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
});

// Public status page — no auth. `data` is short-polled by the page every few seconds.
Route::get('status/{slug}', [StatusController::class, 'show'])->name('status.show');
Route::get('status/{slug}/data', [StatusController::class, 'data'])->name('status.data');
Route::post('status/{slug}/subscribe', [StatusController::class, 'subscribe'])->name('status.subscribe');

// Stripe webhook — signature verified by Cashier's middleware; CSRF-exempt (see bootstrap/app.php).
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware(VerifyWebhookSignature::class)
    ->name('cashier.webhook');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

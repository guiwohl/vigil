<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        $monitors = Monitor::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Monitor $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'url' => $m->url,
                'status' => $m->status,
                'interval_seconds' => $m->interval_seconds,
                'failure_threshold' => $m->failure_threshold,
                'last_checked_at' => $m->last_checked_at?->toIso8601String(),
            ]);

        $incidents = Incident::query()
            ->with('updates')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get()
            ->map(fn (Incident $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'status' => $i->status,
                'is_auto' => $i->is_auto,
                'started_at' => $i->started_at?->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
            ]);

        return Inertia::render('dashboard', [
            'monitors' => $monitors,
            'incidents' => $incidents,
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'plan' => $tenant->plan,
                'monitor_limit' => $tenant->monitor_limit,
            ],
        ]);
    }
}

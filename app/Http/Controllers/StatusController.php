<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public, unauthenticated status page. Because no user is authenticated the
 * BelongsToTenant global scope is inert, so we address the tenant explicitly by
 * slug and read only through its relationships — never leaking other tenants.
 */
class StatusController extends Controller
{
    public function show(string $slug): Response
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        return Inertia::render('status', [
            'status' => $this->snapshot($tenant),
        ]);
    }

    public function data(string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        return response()->json($this->snapshot($tenant));
    }

    public function subscribe(Request $request, string $slug): JsonResponse
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $tenant->subscribers()->create(['email' => $data['email']]);

        return response()->json(['ok' => true], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Tenant $tenant): array
    {
        $monitors = $tenant->monitors()->where('is_active', true)->get();

        $cutoff = now()->subDays(7);
        $incidents = $tenant->incidents()
            ->with('updates')
            ->where(function ($query) use ($cutoff) {
                $query->whereIn('status', Incident::ACTIVE_STATES)
                    ->orWhere('resolved_at', '>=', $cutoff);
            })
            ->orderByDesc('started_at')
            ->get();

        $overall = match (true) {
            $monitors->contains('status', Monitor::DOWN) => 'down',
            $incidents->contains(fn (Incident $i) => in_array($i->status, Incident::ACTIVE_STATES, true)) => 'degraded',
            default => 'operational',
        };

        return [
            'tenant_name' => $tenant->name,
            'slug' => $tenant->slug,
            'overall' => $overall,
            'monitors' => $monitors->map(fn (Monitor $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'status' => $m->status,
                'last_checked_at' => $m->last_checked_at?->toIso8601String(),
            ])->values(),
            'incidents' => $incidents->map(fn (Incident $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'status' => $i->status,
                'started_at' => $i->started_at?->toIso8601String(),
                'resolved_at' => $i->resolved_at?->toIso8601String(),
                'updates' => $i->updates->map(fn ($u) => [
                    'message' => $u->message,
                    'status' => $u->status,
                    'created_at' => $u->created_at?->toIso8601String(),
                ])->values(),
            ])->values(),
        ];
    }
}

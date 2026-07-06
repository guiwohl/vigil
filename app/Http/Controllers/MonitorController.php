<?php

namespace App\Http\Controllers;

use App\Models\Monitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        if ($tenant->monitors()->count() >= $tenant->monitor_limit) {
            abort(402, 'Monitor limit reached. Upgrade your plan to add more.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:2048'],
            'method' => ['sometimes', 'in:GET,HEAD,POST'],
            'interval_seconds' => ['sometimes', 'integer', 'min:5', 'max:86400'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'failure_threshold' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $tenant->monitors()->create([
            ...$data,
            'status' => Monitor::UNKNOWN,
        ]);

        return back();
    }

    public function update(Request $request, Monitor $monitor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'interval_seconds' => ['sometimes', 'integer', 'min:5', 'max:86400'],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'failure_threshold' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $monitor->update($data);

        return back();
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $monitor->delete();

        return back();
    }
}

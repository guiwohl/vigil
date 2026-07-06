<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->domainWord(),
            'url' => fake()->url(),
            'method' => 'GET',
            'interval_seconds' => 60,
            'timeout_seconds' => 10,
            'failure_threshold' => 2,
            'is_active' => true,
            'status' => Monitor::UNKNOWN,
            'consecutive_failures' => 0,
        ];
    }
}

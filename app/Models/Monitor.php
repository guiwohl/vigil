<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    use BelongsToTenant, HasFactory;

    public const UP = 'up';

    public const DOWN = 'down';

    public const PAUSED = 'paused';

    public const UNKNOWN = 'unknown';

    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'method',
        'interval_seconds',
        'timeout_seconds',
        'failure_threshold',
        'is_active',
        'status',
        'consecutive_failures',
        'last_checked_at',
    ];

    protected $casts = [
        'interval_seconds' => 'integer',
        'timeout_seconds' => 'integer',
        'failure_threshold' => 'integer',
        'consecutive_failures' => 'integer',
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function isDue(): bool
    {
        if ($this->last_checked_at === null) {
            return true;
        }

        return $this->last_checked_at->diffInSeconds(now()) >= $this->interval_seconds;
    }
}

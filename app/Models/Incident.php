<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use BelongsToTenant, HasFactory;

    public const OPEN = 'open';

    public const INVESTIGATING = 'investigating';

    public const IDENTIFIED = 'identified';

    public const MONITORING = 'monitoring';

    public const RESOLVED = 'resolved';

    public const ACTIVE_STATES = [
        self::OPEN,
        self::INVESTIGATING,
        self::IDENTIFIED,
        self::MONITORING,
    ];

    protected $fillable = [
        'tenant_id',
        'monitor_id',
        'title',
        'status',
        'is_auto',
        'started_at',
        'resolved_at',
    ];

    protected $casts = [
        'is_auto' => 'boolean',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function updates(): HasMany
    {
        return $this->hasMany(IncidentUpdate::class)->orderBy('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATES);
    }
}

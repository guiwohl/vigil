<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'monitor_id',
        'tenant_id',
        'checked_at',
        'up',
        'status_code',
        'response_time_ms',
        'error',
    ];

    protected $casts = [
        'checked_at' => 'datetime',
        'up' => 'boolean',
        'status_code' => 'integer',
        'response_time_ms' => 'integer',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}

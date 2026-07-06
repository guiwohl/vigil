<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Tenant extends Model
{
    use Billable, HasFactory;

    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'monitor_limit',
        'stripe_subscription_id',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function stripeEmail(): ?string
    {
        return $this->users()->value('email');
    }

    public function stripeName(): ?string
    {
        return $this->name;
    }
}

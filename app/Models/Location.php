<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'address',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function locationProducts(): HasMany
    {
        return $this->hasMany(LocationProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('status', \App\Enums\OrderStatus::activeValues());
    }
}

<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Order extends Model
{
    protected $fillable = [
        'location_id',
        'source',
        'status',
        'estimated_ready_at',
    ];

    protected $casts = [
        'source' => OrderSource::class,
        'status' => OrderStatus::class,
        'estimated_ready_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function locationProducts(): BelongsToMany
    {
        return $this->belongsToMany(LocationProduct::class, 'order_location_product')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', \App\Enums\OrderStatus::activeValues());
    }
}

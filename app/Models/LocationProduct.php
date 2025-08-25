<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LocationProduct extends Model
{
    protected $fillable = [
        'location_id',
        'product_id',
        'is_available',
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_location_product')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}

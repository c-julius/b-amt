<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

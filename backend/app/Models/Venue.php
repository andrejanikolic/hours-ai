<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'brand_id', 'name', 'slug', 'address', 'city', 'country',
        'timezone', 'phone', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function orderTypes(): BelongsToMany
    {
        return $this->belongsToMany(OrderType::class, 'venue_order_types')
            ->withPivot(['id', 'active'])
            ->withTimestamps();
    }

    public function servingTimes(): HasMany
    {
        return $this->hasMany(ServingTime::class, 'parent_id')
            ->where('parent_type', 'venue');
    }
}

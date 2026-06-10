<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'brand_id', 'name', 'internal_name', 'description', 'active', 'position',
    ];

    protected $casts = ['active' => 'boolean'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function servingTimes(): HasMany
    {
        return $this->hasMany(ServingTime::class, 'parent_id')
            ->where('parent_type', 'menu');
    }
}

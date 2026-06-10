<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = ['name', 'slug', 'timezone', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function servingTimes(): HasMany
    {
        return $this->hasMany(ServingTime::class, 'parent_id')
            ->where('parent_type', 'brand');
    }
}

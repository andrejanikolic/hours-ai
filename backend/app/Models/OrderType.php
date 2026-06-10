<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderType extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'slug'];

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'venue_order_types')
            ->withPivot(['id', 'active'])
            ->withTimestamps();
    }
}

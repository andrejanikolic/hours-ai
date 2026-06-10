<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServingTime extends Model
{
    protected $fillable = [
        'parent_type', 'parent_id', 'type', 'days',
        'date', 'date_to', 'time_from', 'time_to', 'working',
    ];

    protected $casts = [
        'days'    => 'array',
        'working' => 'boolean',
    ];
}

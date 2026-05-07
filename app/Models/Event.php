<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'anon_id',
        'name',
        'host',
        'verdict',
        'cached',
        'latency_ms',
        'properties',
        'created_at',
    ];

    protected $casts = [
        'cached' => 'boolean',
        'latency_ms' => 'integer',
        'properties' => 'array',
        'created_at' => 'datetime',
    ];
}

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
        'model',
        'input_tokens',
        'output_tokens',
        'cache_read_input_tokens',
        'cache_creation_input_tokens',
        'cached',
        'latency_ms',
        'properties',
        'created_at',
    ];

    protected $casts = [
        'cached' => 'boolean',
        'latency_ms' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cache_read_input_tokens' => 'integer',
        'cache_creation_input_tokens' => 'integer',
        'properties' => 'array',
        'created_at' => 'datetime',
    ];
}

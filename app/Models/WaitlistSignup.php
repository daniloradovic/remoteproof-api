<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistSignup extends Model
{
    protected $fillable = [
        'email',
        'plan_interest',
        'anon_id',
        'host_referrer',
        'source',
        'user_agent',
        'ip',
    ];
}

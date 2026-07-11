<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPolicyRule extends Model
{
    protected $fillable = [
        'company_id',
        'action_type',
        'subject_role',
        'max_amount',
        'requires_role',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'max_amount' => 'float',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

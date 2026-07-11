<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'actor_type',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'ip_address',
        'meta',
        'created_at',
    ];

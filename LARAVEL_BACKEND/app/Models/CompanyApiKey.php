<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyApiKey extends Model
{
    protected $fillable = [
        'company_id', 'name', 'key_prefix', 'key_hash', 'scopes', 'last_used_at', 'revoked_at', 'created_by',
    ];

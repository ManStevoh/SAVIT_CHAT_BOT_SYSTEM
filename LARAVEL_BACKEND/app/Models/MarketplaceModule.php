<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceModule extends Model
{
    protected $fillable = [
        'module_key',
        'name',
        'description',
        'category',
        'publisher',
        'required_plan',
        'prompt_addon',
        'tools',
        'manifest',
        'is_active',
        'sort_order',
    ];


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyModuleInstallation extends Model
{
    protected $fillable = [
        'company_id',
        'module_key',
        'status',
        'config',
        'installed_at',
    ];

    protected $casts = [

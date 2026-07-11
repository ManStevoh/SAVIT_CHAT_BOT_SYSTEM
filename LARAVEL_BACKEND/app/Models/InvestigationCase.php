<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestigationCase extends Model
{
    protected $fillable = [
        'company_id',
        'owner_analytics_investigation_id',

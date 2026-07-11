<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceOutcome extends Model
{
    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'recommendation_key',
        'recommended_action',
        'outcome',
        'notes',
        'metrics',

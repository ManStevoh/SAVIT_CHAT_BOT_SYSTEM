<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTimelineEvent extends Model
{
    protected $fillable = [
        'company_id',
        'event_type',

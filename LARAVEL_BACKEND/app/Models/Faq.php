<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faq extends Model
{
    protected $fillable = [
        'company_id',
        'question',
        'answer',
        'category',
        'keywords',
        'question_embedding',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'keywords' => 'array',
        'question_embedding' => 'array',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

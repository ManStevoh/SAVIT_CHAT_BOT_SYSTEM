<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeArtifact extends Model
{
    protected $fillable = [
        'company_id', 'artifact_type', 'title', 'content',
        'source_chat_count', 'evidence', 'status',
    ];

    protected $casts = [
        'evidence' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

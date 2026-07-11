<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolProposal extends Model
{
    protected $fillable = [
        'company_id', 'proposed_name', 'description', 'tool_chain',
        'occurrence_count', 'status',
    ];

    protected $casts = [
        'tool_chain' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

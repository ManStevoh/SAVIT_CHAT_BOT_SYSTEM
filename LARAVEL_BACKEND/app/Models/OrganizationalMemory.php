<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationalMemory extends Model
{
    protected $fillable = ['company_id', 'category', 'title', 'content', 'source'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

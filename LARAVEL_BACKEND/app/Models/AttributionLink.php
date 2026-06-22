<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttributionLink extends Model
{
    protected $fillable = [
        'company_id',
        'social_post_id',
        'slug',
        'destination_url',
        'whatsapp_prefill',
        'click_count',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function socialPost(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttributionEvent::class);
    }
}

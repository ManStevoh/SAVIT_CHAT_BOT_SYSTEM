<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventAttendee extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VALID = 'valid';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_USED = 'used';

    protected $fillable = [
        'company_id',
        'event_registration_id',
        'event_id',
        'event_session_id',
        'name',
        'email',
        'phone',
        'ticket_code',
        'qr_secret',
        'checked_in_at',
        'status',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
    ];

    public function publicUrl(): string
    {
        return url('/ticket/'.$this->ticket_code);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(EventCheckIn::class);
    }
}

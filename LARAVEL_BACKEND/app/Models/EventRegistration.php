<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistration extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_WAITLISTED = 'waitlisted';

    protected $fillable = [
        'company_id',
        'event_id',
        'event_session_id',
        'event_ticket_type_id',
        'order_id',
        'order_product_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'quantity',
        'status',
        'hold_expires_at',
        'answers',
    ];

    protected $casts = [
        'quantity' => 'int',
        'hold_expires_at' => 'datetime',
        'answers' => 'array',
    ];

    public function occupiesSeat(): bool
    {
        if ($this->status === self::STATUS_CONFIRMED) {
            return true;
        }
        if ($this->status === self::STATUS_PENDING_PAYMENT) {
            return $this->hold_expires_at === null || $this->hold_expires_at->isFuture();
        }

        return false;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(EventTicketType::class, 'event_ticket_type_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderProduct(): BelongsTo
    {
        return $this->belongsTo(OrderProduct::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_UNLISTED = 'unlisted';

    public const FORMAT_IN_PERSON = 'in_person';

    public const FORMAT_ONLINE = 'online';

    public const FORMAT_HYBRID = 'hybrid';

    protected $fillable = [
        'company_id',
        'event_series_id',
        'title',
        'slug',
        'subtitle',
        'description',
        'cover_image',
        'format',
        'venue_name',
        'venue_address',
        'online_url',
        'timezone',
        'status',
        'visibility',
        'registration_opens_at',
        'registration_closes_at',
        'organizer_name',
        'organizer_contact',
        'custom_fields',
        'cancellation_policy',
    ];

    protected $casts = [
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (empty($event->slug) && ! empty($event->title)) {
                $event->slug = static::generateUniqueSlug($event->company_id, $event->title);
            }
        });
    }

    public static function generateUniqueSlug(?int $companyId, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'event';
        }

        $slug = $base;
        $suffix = 2;
        while (
            static::where('company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function registrationIsOpen(): bool
    {
        if (! $this->isPublished() || $this->status === self::STATUS_CANCELLED) {
            return false;
        }
        $now = now();
        if ($this->registration_opens_at && $now->lt($this->registration_opens_at)) {
            return false;
        }
        if ($this->registration_closes_at && $now->gt($this->registration_closes_at)) {
            return false;
        }

        return true;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class, 'event_series_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('starts_at');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(EventTicketType::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function publicUrl(): ?string
    {
        $slug = $this->company?->store_slug;
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return url('/s/'.$slug.'/e/'.$this->slug);
    }
}

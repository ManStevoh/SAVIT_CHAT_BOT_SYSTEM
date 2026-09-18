<?php

namespace App\Services\Events;

use App\Models\Company;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventTicketType;
use App\Models\Product;
use App\Support\MoneyFormatter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventService
{
    public const HOLD_MINUTES = 45;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createEvent(Company $company, array $data): Event
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Event title is required.']);
        }

        $slug = isset($data['slug']) ? Str::slug((string) $data['slug']) : '';
        if ($slug === '') {
            $slug = Event::generateUniqueSlug($company->id, $title);
        } else {
            $slug = Event::generateUniqueSlug($company->id, $slug);
        }

        $this->ensureStoreSlug($company);

        return Event::create([
            'company_id' => $company->id,
            'event_series_id' => $data['eventSeriesId'] ?? $data['event_series_id'] ?? null,
            'title' => $title,
            'slug' => $slug,
            'subtitle' => $this->nullableString($data['subtitle'] ?? null),
            'description' => $this->nullableString($data['description'] ?? null),
            'cover_image' => $this->nullableString($data['coverImage'] ?? $data['cover_image'] ?? null),
            'format' => $this->format($data['format'] ?? Event::FORMAT_IN_PERSON),
            'venue_name' => $this->nullableString($data['venueName'] ?? $data['venue_name'] ?? null),
            'venue_address' => $this->nullableString($data['venueAddress'] ?? $data['venue_address'] ?? null),
            'online_url' => $this->nullableString($data['onlineUrl'] ?? $data['online_url'] ?? null),
            'timezone' => $this->timezone($data['timezone'] ?? null, $company),
            'status' => Event::STATUS_DRAFT,
            'visibility' => $this->visibility($data['visibility'] ?? Event::VISIBILITY_UNLISTED),
            'registration_opens_at' => $data['registrationOpensAt'] ?? $data['registration_opens_at'] ?? null,
            'registration_closes_at' => $data['registrationClosesAt'] ?? $data['registration_closes_at'] ?? null,
            'organizer_name' => $this->nullableString($data['organizerName'] ?? $data['organizer_name'] ?? $company->name),
            'organizer_contact' => $this->nullableString($data['organizerContact'] ?? $data['organizer_contact'] ?? null),
            'custom_fields' => $data['customFields'] ?? $data['custom_fields'] ?? [],
            'cancellation_policy' => $this->nullableString($data['cancellationPolicy'] ?? $data['cancellation_policy'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEvent(Event $event, array $data): Event
    {
        $updates = [];
        foreach ([
            'title' => 'title',
            'subtitle' => 'subtitle',
            'description' => 'description',
            'coverImage' => 'cover_image',
            'cover_image' => 'cover_image',
            'format' => 'format',
            'venueName' => 'venue_name',
            'venue_name' => 'venue_name',
            'venueAddress' => 'venue_address',
            'venue_address' => 'venue_address',
            'onlineUrl' => 'online_url',
            'online_url' => 'online_url',
            'timezone' => 'timezone',
            'visibility' => 'visibility',
            'status' => 'status',
            'organizerName' => 'organizer_name',
            'organizer_name' => 'organizer_name',
            'organizerContact' => 'organizer_contact',
            'organizer_contact' => 'organizer_contact',
            'cancellationPolicy' => 'cancellation_policy',
            'cancellation_policy' => 'cancellation_policy',
            'registrationOpensAt' => 'registration_opens_at',
            'registration_opens_at' => 'registration_opens_at',
            'registrationClosesAt' => 'registration_closes_at',
            'registration_closes_at' => 'registration_closes_at',
            'customFields' => 'custom_fields',
            'custom_fields' => 'custom_fields',
            'eventSeriesId' => 'event_series_id',
            'event_series_id' => 'event_series_id',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input];
            }
        }
        if (isset($updates['format'])) {
            $updates['format'] = $this->format($updates['format']);
        }
        if (isset($updates['visibility'])) {
            $updates['visibility'] = $this->visibility($updates['visibility']);
        }
        if (isset($updates['status']) && ! in_array($updates['status'], [
            Event::STATUS_DRAFT, Event::STATUS_PUBLISHED, Event::STATUS_CANCELLED, Event::STATUS_COMPLETED,
        ], true)) {
            unset($updates['status']);
        }
        if (array_key_exists('slug', $data) && is_string($data['slug']) && trim($data['slug']) !== '') {
            $updates['slug'] = Event::generateUniqueSlug($event->company_id, $data['slug'], $event->id);
        }
        if ($updates !== []) {
            $event->update($updates);
        }
        $event->ticketTypes->each(fn (EventTicketType $type) => $this->syncTicketProduct($type->fresh('event')));

        return $event->fresh(['sessions', 'ticketTypes']);
    }

    public function publish(Event $event): Event
    {
        if ($event->sessions()->count() < 1) {
            throw ValidationException::withMessages(['sessions' => 'Add at least one session before publishing.']);
        }
        if ($event->ticketTypes()->where('status', EventTicketType::STATUS_ACTIVE)->count() < 1) {
            throw ValidationException::withMessages(['ticketTypes' => 'Add at least one ticket type before publishing.']);
        }
        $event->update(['status' => Event::STATUS_PUBLISHED]);
        $event->ticketTypes->each(fn (EventTicketType $type) => $this->syncTicketProduct($type->fresh('event')));

        return $event->fresh(['sessions', 'ticketTypes']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSession(Event $event, array $data): EventSession
    {
        $startsAt = $data['startsAt'] ?? $data['starts_at'] ?? null;
        $endsAt = $data['endsAt'] ?? $data['ends_at'] ?? null;
        if (! $startsAt || ! $endsAt) {
            throw ValidationException::withMessages(['startsAt' => 'Session start and end times are required.']);
        }

        return EventSession::create([
            'company_id' => $event->company_id,
            'event_id' => $event->id,
            'title' => $this->nullableString($data['title'] ?? null),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => isset($data['capacity']) && $data['capacity'] !== '' && $data['capacity'] !== null
                ? (int) $data['capacity']
                : null,
            'venue_name' => $this->nullableString($data['venueName'] ?? $data['venue_name'] ?? null),
            'venue_address' => $this->nullableString($data['venueAddress'] ?? $data['venue_address'] ?? null),
            'online_url' => $this->nullableString($data['onlineUrl'] ?? $data['online_url'] ?? null),
            'status' => EventSession::STATUS_SCHEDULED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSession(EventSession $session, array $data): EventSession
    {
        $updates = [];
        foreach ([
            'title' => 'title',
            'startsAt' => 'starts_at',
            'starts_at' => 'starts_at',
            'endsAt' => 'ends_at',
            'ends_at' => 'ends_at',
            'capacity' => 'capacity',
            'venueName' => 'venue_name',
            'venue_name' => 'venue_name',
            'venueAddress' => 'venue_address',
            'venue_address' => 'venue_address',
            'onlineUrl' => 'online_url',
            'online_url' => 'online_url',
            'status' => 'status',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input] === '' ? null : $data[$input];
            }
        }
        if (array_key_exists('capacity', $updates) && $updates['capacity'] !== null) {
            $updates['capacity'] = (int) $updates['capacity'];
        }
        if ($updates !== []) {
            $session->update($updates);
        }

        return $session->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTicketType(Event $event, array $data): EventTicketType
    {
        $name = trim((string) ($data['name'] ?? 'General admission'));
        $price = round((float) ($data['price'] ?? 0), 2);
        $isFree = (bool) ($data['isFree'] ?? $data['is_free'] ?? ($price <= 0));
        if ($isFree) {
            $price = 0;
        }

        $ticket = EventTicketType::create([
            'company_id' => $event->company_id,
            'event_id' => $event->id,
            'event_session_id' => $data['sessionId'] ?? $data['event_session_id'] ?? $data['eventSessionId'] ?? null,
            'name' => $name !== '' ? $name : 'General admission',
            'description' => $this->nullableString($data['description'] ?? null),
            'price' => $price,
            'currency' => strtoupper((string) ($data['currency'] ?? $event->company?->settings?->displayCurrencyCode() ?? 'KES')),
            'quantity_total' => isset($data['quantityTotal']) || isset($data['quantity_total'])
                ? (($data['quantityTotal'] ?? $data['quantity_total']) !== null && ($data['quantityTotal'] ?? $data['quantity_total']) !== ''
                    ? (int) ($data['quantityTotal'] ?? $data['quantity_total'])
                    : null)
                : null,
            'sales_start_at' => $data['salesStartAt'] ?? $data['sales_start_at'] ?? null,
            'sales_end_at' => $data['salesEndAt'] ?? $data['sales_end_at'] ?? null,
            'min_per_order' => max(1, (int) ($data['minPerOrder'] ?? $data['min_per_order'] ?? 1)),
            'max_per_order' => max(1, (int) ($data['maxPerOrder'] ?? $data['max_per_order'] ?? 10)),
            'is_free' => $isFree,
            'status' => EventTicketType::STATUS_ACTIVE,
        ]);

        $this->syncTicketProduct($ticket->fresh('event'));

        return $ticket->fresh('product');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTicketType(EventTicketType $ticket, array $data): EventTicketType
    {
        $updates = [];
        foreach ([
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'currency' => 'currency',
            'quantityTotal' => 'quantity_total',
            'quantity_total' => 'quantity_total',
            'salesStartAt' => 'sales_start_at',
            'sales_start_at' => 'sales_start_at',
            'salesEndAt' => 'sales_end_at',
            'sales_end_at' => 'sales_end_at',
            'minPerOrder' => 'min_per_order',
            'min_per_order' => 'min_per_order',
            'maxPerOrder' => 'max_per_order',
            'max_per_order' => 'max_per_order',
            'isFree' => 'is_free',
            'is_free' => 'is_free',
            'sessionId' => 'event_session_id',
            'eventSessionId' => 'event_session_id',
            'event_session_id' => 'event_session_id',
            'status' => 'status',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $updates[$column] = $data[$input] === '' ? null : $data[$input];
            }
        }
        if (isset($updates['is_free'])) {
            $updates['is_free'] = (bool) $updates['is_free'];
            if ($updates['is_free']) {
                $updates['price'] = 0;
            }
        }
        if ($updates !== []) {
            $ticket->update($updates);
        }
        $this->syncTicketProduct($ticket->fresh('event'));

        return $ticket->fresh('product');
    }

    public function syncTicketProduct(EventTicketType $ticket): Product
    {
        $event = $ticket->event ?? $ticket->load('event')->event;
        $company = $event?->company ?? Company::find($ticket->company_id);
        $remaining = $this->ticketRemaining($ticket);
        $status = ($event && $event->isPublished() && $ticket->status === EventTicketType::STATUS_ACTIVE)
            ? 'active'
            : 'draft';
        $name = trim(($event?->title ?: 'Event').' — '.$ticket->name);

        $payload = [
            'company_id' => $ticket->company_id,
            'name' => $name,
            'description' => $ticket->description ?: ($event?->description),
            'price' => $ticket->price,
            'category' => 'Events',
            'product_type' => 'event',
            'fulfillment_type' => 'ticket',
            'track_inventory' => $ticket->quantity_total !== null,
            'requires_delivery_address' => false,
            'stock' => $remaining,
            'status' => $status,
            'access_url' => $event?->publicUrl(),
        ];

        if ($ticket->product_id) {
            $product = Product::where('company_id', $ticket->company_id)->find($ticket->product_id);
            if ($product) {
                $product->update($payload);

                return $product->fresh();
            }
        }

        $product = Product::create($payload);
        $ticket->update(['product_id' => $product->id]);

        return $product;
    }

    public function ticketRemaining(EventTicketType $ticket): int
    {
        if ($ticket->quantity_total === null) {
            return 9999;
        }

        $taken = app(EventRegistrationService::class)->occupiedQuantityForTicket($ticket);

        return max(0, (int) $ticket->quantity_total - $taken);
    }

    public function sessionRemaining(EventSession $session): ?int
    {
        if ($session->capacity === null) {
            return null;
        }
        $taken = app(EventRegistrationService::class)->occupiedQuantityForSession($session);

        return max(0, (int) $session->capacity - $taken);
    }

    public function publicUrl(Event $event): string
    {
        $company = $event->company ?? $event->load('company')->company;
        $this->ensureStoreSlug($company);
        $company->refresh();

        return url('/s/'.$company->store_slug.'/e/'.$event->slug);
    }

    /**
     * @return array{manual: ?string, bank: ?string, details: list<string>}
     */
    public function paymentDetails(Company $company): array
    {
        $company->loadMissing('settings');
        $settings = $company->settings;
        $manual = is_string($settings?->order_payment_manual_instructions)
            ? trim($settings->order_payment_manual_instructions)
            : '';
        $bank = is_string($settings?->bank_transfer_instructions)
            ? trim($settings->bank_transfer_instructions)
            : '';
        $details = [];
        if ($bank !== '') {
            $details[] = $bank;
        }
        if ($manual !== '' && $manual !== $bank) {
            $details[] = $manual;
        }

        return [
            'manual' => $manual !== '' ? $manual : null,
            'bank' => $bank !== '' ? $bank : null,
            'details' => $details,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEvent(Event $event, bool $includePrivate = false): array
    {
        $event->loadMissing(['sessions', 'ticketTypes.product', 'company.settings']);
        $currency = $event->company?->settings?->displayCurrencyCode() ?? 'KES';

        $payload = [
            'id' => (string) $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'subtitle' => $event->subtitle,
            'description' => $event->description,
            'coverImage' => $event->cover_image,
            'format' => $event->format,
            'venueName' => $event->venue_name,
            'venueAddress' => $event->venue_address,
            'onlineUrl' => $includePrivate ? $event->online_url : null,
            'timezone' => $event->timezone,
            'status' => $event->status,
            'visibility' => $event->visibility,
            'registrationOpensAt' => $event->registration_opens_at?->toIso8601String(),
            'registrationClosesAt' => $event->registration_closes_at?->toIso8601String(),
            'organizerName' => $event->organizer_name,
            'organizerContact' => $event->organizer_contact,
            'customFields' => $event->custom_fields ?? [],
            'cancellationPolicy' => $event->cancellation_policy,
            'publicUrl' => $this->publicUrl($event),
            'registrationOpen' => $event->registrationIsOpen(),
            'sessions' => $event->sessions->map(fn (EventSession $session) => $this->serializeSession($session))->values()->all(),
            'ticketTypes' => $event->ticketTypes
                ->filter(fn (EventTicketType $t) => $includePrivate || $t->status === EventTicketType::STATUS_ACTIVE)
                ->map(fn (EventTicketType $t) => $this->serializeTicketType($t, $currency))
                ->values()
                ->all(),
        ];

        if ($includePrivate) {
            $payload['onlineUrl'] = $event->online_url;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSession(EventSession $session): array
    {
        $remaining = $this->sessionRemaining($session);

        return [
            'id' => (string) $session->id,
            'eventId' => (string) $session->event_id,
            'title' => $session->displayTitle(),
            'startsAt' => $session->starts_at?->toIso8601String(),
            'endsAt' => $session->ends_at?->toIso8601String(),
            'capacity' => $session->capacity,
            'remaining' => $remaining,
            'soldOut' => $remaining === 0,
            'venueName' => $session->venue_name,
            'venueAddress' => $session->venue_address,
            'status' => $session->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTicketType(EventTicketType $ticket, ?string $currency = null): array
    {
        $remaining = $this->ticketRemaining($ticket);
        $currency = $currency ?: $ticket->currency;
        $price = (float) $ticket->price;

        return [
            'id' => (string) $ticket->id,
            'eventId' => (string) $ticket->event_id,
            'sessionId' => $ticket->event_session_id ? (string) $ticket->event_session_id : null,
            'productId' => $ticket->product_id ? (string) $ticket->product_id : null,
            'name' => $ticket->name,
            'description' => $ticket->description,
            'price' => $price,
            'priceFormatted' => MoneyFormatter::formatFromSettings($price, $ticket->event?->company?->settings),
            'currency' => $currency,
            'quantityTotal' => $ticket->quantity_total,
            'remaining' => $ticket->quantity_total === null ? null : $remaining,
            'soldOut' => $ticket->quantity_total !== null && $remaining <= 0,
            'salesStartAt' => $ticket->sales_start_at?->toIso8601String(),
            'salesEndAt' => $ticket->sales_end_at?->toIso8601String(),
            'minPerOrder' => $ticket->min_per_order,
            'maxPerOrder' => $ticket->max_per_order,
            'isFree' => (bool) $ticket->is_free || $price <= 0,
            'onSale' => $ticket->isOnSale(),
            'status' => $ticket->status,
        ];
    }

    public function eventsEnabled(Company $company): bool
    {
        $company->loadMissing('settings');

        return (bool) ($company->settings?->enable_events ?? true);
    }

    private function ensureStoreSlug(?Company $company): void
    {
        if (! $company) {
            return;
        }
        if (is_string($company->store_slug) && trim($company->store_slug) !== '') {
            return;
        }
        $company->update([
            'store_slug' => Company::uniqueStoreSlugFromName((string) $company->name, $company->id),
            'storefront_enabled' => true,
        ]);
    }

    private function format(mixed $value): string
    {
        $v = strtolower((string) $value);

        return in_array($v, [Event::FORMAT_IN_PERSON, Event::FORMAT_ONLINE, Event::FORMAT_HYBRID], true)
            ? $v
            : Event::FORMAT_IN_PERSON;
    }

    private function visibility(mixed $value): string
    {
        $v = strtolower((string) $value);

        return in_array($v, [Event::VISIBILITY_PUBLIC, Event::VISIBILITY_UNLISTED], true)
            ? $v
            : Event::VISIBILITY_UNLISTED;
    }

    private function timezone(mixed $value, Company $company): string
    {
        $tz = is_string($value) && trim($value) !== '' ? trim($value) : null;

        return $tz ?: ($company->settings?->timezone ?: 'Africa/Nairobi');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}

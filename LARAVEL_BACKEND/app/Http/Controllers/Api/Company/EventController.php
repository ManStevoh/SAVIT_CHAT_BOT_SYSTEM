<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Models\EventSession;
use App\Models\EventTicketType;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        protected EventService $events,
        protected EventRegistrationService $registrations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        if (! $this->events->eventsEnabled($company)) {
            return response()->json(['success' => false, 'message' => 'Events are disabled.', 'code' => 'events_disabled'], 403);
        }

        $query = Event::where('company_id', $company->id)->with(['sessions', 'ticketTypes'])->orderByDesc('id');
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->string('status'));
        }

        $items = $query->limit(200)->get()->map(fn (Event $event) => $this->events->serializeEvent($event, true))->values()->all();

        return response()->json([
            'events' => $items,
            'enabled' => true,
            'publicEventsUrl' => $company->store_slug ? url('/s/'.$company->store_slug.'/events') : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:80|alpha_dash',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:20000',
            'coverImage' => 'nullable|string|max:2048',
            'format' => 'nullable|in:in_person,online,hybrid',
            'venueName' => 'nullable|string|max:255',
            'venueAddress' => 'nullable|string|max:2000',
            'onlineUrl' => 'nullable|url|max:2048',
            'timezone' => 'nullable|string|max:64',
            'visibility' => 'nullable|in:public,unlisted',
            'registrationOpensAt' => 'nullable|date',
            'registrationClosesAt' => 'nullable|date',
            'organizerName' => 'nullable|string|max:255',
            'organizerContact' => 'nullable|string|max:255',
            'cancellationPolicy' => 'nullable|string|max:5000',
            'customFields' => 'nullable|array',
            'session' => 'nullable|array',
            'session.title' => 'nullable|string|max:255',
            'session.startsAt' => 'nullable|date',
            'session.endsAt' => 'nullable|date|after:session.startsAt',
            'session.capacity' => 'nullable|integer|min:1',
            'ticket' => 'nullable|array',
            'ticket.name' => 'nullable|string|max:255',
            'ticket.price' => 'nullable|numeric|min:0',
            'ticket.quantityTotal' => 'nullable|integer|min:1',
            'ticket.isFree' => 'nullable|boolean',
        ]);

        $event = $this->events->createEvent($company, $validated);

        if (! empty($validated['session']['startsAt']) && ! empty($validated['session']['endsAt'])) {
            $session = $this->events->createSession($event, $validated['session']);
            if (! empty($validated['ticket'])) {
                $ticketData = $validated['ticket'];
                $ticketData['sessionId'] = $session->id;
                $this->events->createTicketType($event, $ticketData);
            }
        } elseif (! empty($validated['ticket'])) {
            $this->events->createTicketType($event, $validated['ticket']);
        }

        return response()->json([
            'success' => true,
            'event' => $this->events->serializeEvent($event->fresh(['sessions', 'ticketTypes']), true),
        ], 201);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'event' => $this->events->serializeEvent($event, true),
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:80|alpha_dash',
            'subtitle' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:20000',
            'coverImage' => 'nullable|string|max:2048',
            'format' => 'nullable|in:in_person,online,hybrid',
            'venueName' => 'nullable|string|max:255',
            'venueAddress' => 'nullable|string|max:2000',
            'onlineUrl' => 'nullable|url|max:2048',
            'timezone' => 'nullable|string|max:64',
            'visibility' => 'nullable|in:public,unlisted',
            'status' => 'nullable|in:draft,published,cancelled,completed',
            'registrationOpensAt' => 'nullable|date',
            'registrationClosesAt' => 'nullable|date',
            'organizerName' => 'nullable|string|max:255',
            'organizerContact' => 'nullable|string|max:255',
            'cancellationPolicy' => 'nullable|string|max:5000',
            'customFields' => 'nullable|array',
        ]);

        $event = $this->events->updateEvent($event, $validated);

        return response()->json([
            'success' => true,
            'event' => $this->events->serializeEvent($event, true),
        ]);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $event->delete();

        return response()->json(['success' => true]);
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $event = $this->events->publish($event);

        return response()->json([
            'success' => true,
            'event' => $this->events->serializeEvent($event, true),
        ]);
    }

    public function storeSession(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'startsAt' => 'required|date',
            'endsAt' => 'required|date|after:startsAt',
            'capacity' => 'nullable|integer|min:1',
            'venueName' => 'nullable|string|max:255',
            'venueAddress' => 'nullable|string|max:2000',
            'onlineUrl' => 'nullable|url|max:2048',
        ]);

        $session = $this->events->createSession($event, $validated);

        return response()->json([
            'success' => true,
            'session' => $this->events->serializeSession($session),
        ], 201);
    }

    public function updateSession(Request $request, Event $event, EventSession $session): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id || $session->event_id !== $event->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'startsAt' => 'sometimes|date',
            'endsAt' => 'sometimes|date',
            'capacity' => 'nullable|integer|min:1',
            'venueName' => 'nullable|string|max:255',
            'venueAddress' => 'nullable|string|max:2000',
            'onlineUrl' => 'nullable|url|max:2048',
            'status' => 'nullable|in:scheduled,cancelled,completed',
        ]);

        $session = $this->events->updateSession($session, $validated);

        return response()->json([
            'success' => true,
            'session' => $this->events->serializeSession($session),
        ]);
    }

    public function destroySession(Request $request, Event $event, EventSession $session): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id || $session->event_id !== $event->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $session->delete();

        return response()->json(['success' => true]);
    }

    public function storeTicketType(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0',
            'sessionId' => 'nullable|integer',
            'quantityTotal' => 'nullable|integer|min:1',
            'salesStartAt' => 'nullable|date',
            'salesEndAt' => 'nullable|date',
            'minPerOrder' => 'nullable|integer|min:1',
            'maxPerOrder' => 'nullable|integer|min:1',
            'isFree' => 'nullable|boolean',
        ]);

        $ticket = $this->events->createTicketType($event, $validated);

        return response()->json([
            'success' => true,
            'ticketType' => $this->events->serializeTicketType($ticket),
        ], 201);
    }

    public function updateTicketType(Request $request, Event $event, EventTicketType $ticketType): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id || $ticketType->event_id !== $event->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0',
            'sessionId' => 'nullable|integer',
            'quantityTotal' => 'nullable|integer|min:1',
            'salesStartAt' => 'nullable|date',
            'salesEndAt' => 'nullable|date',
            'minPerOrder' => 'nullable|integer|min:1',
            'maxPerOrder' => 'nullable|integer|min:1',
            'isFree' => 'nullable|boolean',
            'status' => 'nullable|in:active,archived',
        ]);

        $ticket = $this->events->updateTicketType($ticketType, $validated);

        return response()->json([
            'success' => true,
            'ticketType' => $this->events->serializeTicketType($ticket),
        ]);
    }

    public function destroyTicketType(Request $request, Event $event, EventTicketType $ticketType): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id || $ticketType->event_id !== $event->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $ticketType->update(['status' => EventTicketType::STATUS_ARCHIVED]);
        $this->events->syncTicketProduct($ticketType->fresh('event'));

        return response()->json(['success' => true]);
    }

    public function registrations(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = EventRegistration::where('event_id', $event->id)
            ->with(['session', 'ticketType', 'attendees', 'order'])
            ->orderByDesc('id');
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->string('status'));
        }

        $items = $query->limit(500)->get()
            ->map(fn (EventRegistration $r) => $this->registrations->serializeRegistration($r))
            ->values()
            ->all();

        return response()->json(['registrations' => $items]);
    }

    public function cancelRegistration(Request $request, Event $event, EventRegistration $registration): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id || $registration->event_id !== $event->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $registration = $this->registrations->cancel($registration);

        return response()->json([
            'success' => true,
            'registration' => $this->registrations->serializeRegistration($registration),
        ]);
    }

    public function checkIn(Request $request, Event $event, EventAttendee $attendee): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id || $attendee->event_id !== $event->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $source = $request->string('source', 'dashboard')->toString();
        $attendee = $this->registrations->checkIn($attendee, $request->user(), in_array($source, ['dashboard', 'scan', 'manual'], true) ? $source : 'dashboard');

        return response()->json([
            'success' => true,
            'attendee' => $this->registrations->serializeAttendee($attendee),
        ]);
    }

    public function lookupTicket(Request $request, Event $event): JsonResponse
    {
        $company = $this->requireCompany($request);
        if ($company instanceof JsonResponse) {
            return $company;
        }
        if ($event->company_id !== $company->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $code = strtoupper(trim((string) $request->query('code', $request->input('code', ''))));
        if ($code === '') {
            return response()->json(['message' => 'Ticket code is required.'], 422);
        }

        $attendee = EventAttendee::where('event_id', $event->id)
            ->where('ticket_code', $code)
            ->first();
        if (! $attendee) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'attendee' => $this->registrations->serializeAttendee($attendee),
        ]);
    }

    /**
     * @return \App\Models\Company|JsonResponse
     */
    private function requireCompany(Request $request)
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        if (! $this->events->eventsEnabled($company)) {
            return response()->json(['success' => false, 'message' => 'Events are disabled.', 'code' => 'events_disabled'], 403);
        }

        return $company;
    }
}

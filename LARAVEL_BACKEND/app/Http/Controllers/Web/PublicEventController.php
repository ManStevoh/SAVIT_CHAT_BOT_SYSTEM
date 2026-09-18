<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Services\Cms\CmsSeoService;
use App\Services\Events\EventRegistrationService;
use App\Services\Events\EventService;
use App\Services\Storefront\StorefrontService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PublicEventController extends Controller
{
    public function __construct(
        protected StorefrontService $storefront,
        protected EventService $events,
        protected EventRegistrationService $registrations,
        protected CmsSeoService $seo,
    ) {}

    public function index(string $slug, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        if (! $this->events->eventsEnabled($company)) {
            abort(404);
        }

        $items = Event::where('company_id', $company->id)
            ->where('status', Event::STATUS_PUBLISHED)
            ->where('visibility', Event::VISIBILITY_PUBLIC)
            ->with(['sessions', 'ticketTypes'])
            ->orderBy('id')
            ->get()
            ->map(fn (Event $event) => $this->events->serializeEvent($event, false))
            ->values()
            ->all();

        return Inertia::render('store/events', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'events' => $items,
            'paymentDetails' => $this->events->paymentDetails($company),
            'seo' => $this->seo->forStorefrontContentPage(
                $company,
                'events',
                'Events — '.$company->name,
                'Upcoming events and registration for '.$company->name.'.'
            ),
        ]);
    }

    public function show(string $slug, string $eventSlug, Request $request): Response
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        if (! $this->events->eventsEnabled($company)) {
            abort(404);
        }

        $event = Event::where('company_id', $company->id)
            ->where('slug', $eventSlug)
            ->with(['sessions', 'ticketTypes'])
            ->firstOrFail();

        if ($event->status === Event::STATUS_DRAFT) {
            abort(404);
        }

        return Inertia::render('store/event', [
            'slug' => $slug,
            'company' => $this->companyPayload($company),
            'event' => $this->events->serializeEvent($event, false),
            'paymentDetails' => $this->events->paymentDetails($company),
            'seo' => $this->seo->forStorefrontContentPage(
                $company,
                'e/'.$event->slug,
                $event->title.' — '.$company->name,
                $event->subtitle ?: $event->description
            ),
        ]);
    }

    public function register(string $slug, string $eventSlug, Request $request): RedirectResponse
    {
        $company = $this->storefront->resolveCompanyBySlug($slug);
        $event = Event::where('company_id', $company->id)
            ->where('slug', $eventSlug)
            ->where('status', Event::STATUS_PUBLISHED)
            ->firstOrFail();

        $validated = $request->validate([
            'sessionId' => 'required|integer',
            'ticketTypeId' => 'required|integer',
            'quantity' => 'nullable|integer|min:1|max:50',
            'buyerName' => 'required|string|max:255',
            'buyerEmail' => 'nullable|email|max:255',
            'buyerPhone' => 'nullable|string|max:40',
            'answers' => 'nullable|array',
        ]);

        try {
            $result = $this->registrations->register($company, $event, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($result['free']) {
            $ticket = $result['registration']->attendees->first();
            if ($ticket) {
                return redirect()->to($ticket->publicUrl());
            }
        }

        return redirect()->to($result['payUrl']);
    }

    public function ticket(string $ticketCode): Response
    {
        $attendee = EventAttendee::where('ticket_code', strtoupper(trim($ticketCode)))
            ->with(['event.company.settings', 'session', 'registration'])
            ->firstOrFail();

        $includeJoin = in_array($attendee->status, [EventAttendee::STATUS_VALID, EventAttendee::STATUS_USED], true)
            && $attendee->registration?->status === \App\Models\EventRegistration::STATUS_CONFIRMED;

        return Inertia::render('store/ticket', [
            'attendee' => $this->registrations->serializeAttendee($attendee, $includeJoin),
            'company' => [
                'name' => $attendee->event?->company?->name ?? 'Event',
            ],
            'seo' => $this->seo->noindex('Ticket '.$attendee->ticket_code),
        ]);
    }

    public function ticketIcs(string $ticketCode): SymfonyResponse
    {
        $attendee = EventAttendee::where('ticket_code', strtoupper(trim($ticketCode)))->firstOrFail();
        $ics = $this->registrations->attendeeIcs($attendee);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$attendee->ticket_code.'.ics"',
        ]);
    }

    /** @return array<string, mixed> */
    private function companyPayload($company): array
    {
        $company->loadMissing('settings');
        $settings = $company->settings;
        $theme = is_array($company->storefront_theme) ? $company->storefront_theme : [];

        return [
            'name' => $company->name,
            'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
            'currency' => $settings?->displayCurrencyCode() ?? 'KES',
            'whatsappUrl' => null,
            'theme' => $theme,
            'termsUrl' => '/s/'.$company->store_slug.'/terms',
            'aboutUrl' => '/s/'.$company->store_slug.'/about',
            'eventsUrl' => '/s/'.$company->store_slug.'/events',
        ];
    }
}

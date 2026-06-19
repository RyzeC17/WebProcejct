<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeedbackRequest;
use App\Http\Requests\RegistrationNoteRequest;
use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\Registration;
use App\Services\ApiResponse;
use App\Services\EventService;
use App\Services\FeedbackService;
use App\Services\RegistrationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request, EventService $events): View
    {
        $events->syncExpiredEventsStatuses();
        $query = Event::query()
            ->publicVisible()
            ->with('createdBy')
            ->withRegistrationCounts()
            ->orderBy('inizio_il')
            ->orderBy('titolo');

        $search = trim((string) $request->query('q', ''));
        $eventType = trim((string) $request->query('event_type', ''));
        $eventDate = trim((string) $request->query('date', ''));
        $availability = trim((string) $request->query('availability', ''));

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('titolo', 'like', "%{$search}%")
                    ->orWhere('descrizione', 'like', "%{$search}%")
                    ->orWhere('nome_luogo', 'like', "%{$search}%");
            });
        }

        if ($eventType !== '') {
            $query->where('tipo_evento', $eventType);
        }

        if ($eventDate !== '') {
            try {
                $query->whereDate('inizio_il', Carbon::parse($eventDate)->toDateString());
            } catch (\Throwable) {
                $query->whereRaw('1 = 0');
            }
        }

        $items = $query->get();
        if ($availability !== '') {
            $items = $items->filter(fn (Event $event) => $event->operational_state === $availability)->values();
        }

        $data = [
            'events' => $items,
            'eventTypes' => Event::EVENT_TYPES,
            'filters' => compact('search', 'eventType', 'eventDate', 'availability'),
        ];

        if ($request->ajax()) {
            return view('events.partials.event_cards', $data);
        }

        return view('events.index', $data);
    }

    public function detail(string $slug, Request $request, EventService $events, FeedbackService $feedbacks): View
    {
        $query = Event::query()
            ->with('createdBy', 'customFields.options')
            ->withRegistrationCounts();
        if (! $request->user()?->hasRole('admin')) {
            $query->publicVisible();
        }

        $event = $query->where('slug', $slug)->firstOrFail();
        $events->syncEventStatus($event);
        $event->refresh()->load('customFields.options');
        $event->loadRegistrationCounts();

        if (! $request->user()?->hasRole('admin') && ! in_array($event->stato, [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED], true)) {
            abort(404);
        }

        $registration = null;
        if ($request->user()) {
            $registration = Registration::query()
                ->where('evento_id', $event->id)
                ->where('utente_id', $request->user()->id)
                ->with('customAnswers.field', 'customAnswers.selectedOption')
                ->first();
        }

        $context = [
            'event' => $event,
            'registration' => $registration,
        ];

        if ($event->stato === Event::STATUS_COMPLETED) {
            $context['feedbackSummary'] = $feedbacks->summary($event);
            $context['feedbacks'] = $event->feedbacks()->with('user')->limit(20)->get();
            $context['userFeedback'] = $request->user()
                ? EventFeedback::query()->where('evento_id', $event->id)->where('utente_id', $request->user()->id)->first()
                : null;
            $context['canLeaveFeedback'] = $request->user()
                && $registration
                && $registration->stato === Registration::STATUS_ACTIVE;
        }

        return view('events.detail', $context);
    }

    public function calendar(): View
    {
        return view('events.calendar');
    }

    public function myRegistrations(Request $request, EventService $events): View
    {
        $events->syncExpiredEventsStatuses();

        return view('events.my_registrations', [
            'registrations' => Registration::query()
                ->where('utente_id', $request->user()->id)
                ->with('event')
                ->latest('creato_il')
                ->get(),
        ]);
    }

    public function registerToEvent(string $slug, Request $request, RegistrationService $registrations): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $event = Event::query()
            ->with('customFields.options')
            ->withRegistrationCounts()
            ->where('slug', $slug)
            ->firstOrFail();
        $payload = $this->payload($request);

        $validator = Validator::make($payload, [
            'attendee_note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            return ApiResponse::json('Controlla i dati inseriti.', false, [], $validator->errors()->toArray(), 400);
        }

        try {
            $customAnswers = $registrations->customAnswersFromPayload($event, $payload);
            $registration = $registrations->createOrReactivate(
                $event,
                $request->user(),
                trim((string) ($payload['attendee_note'] ?? '')),
                $customAnswers,
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception, 'Controlla i dati inseriti.');
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json(
            $registration->stato === Registration::STATUS_WAITLISTED
                ? "Richiesta registrata in lista d'attesa."
                : 'Iscrizione salvata con successo.',
            true,
            [
                'registration_id' => $registration->id,
                'status' => $registration->stato,
                'remaining_seats' => $event->refresh()->remaining_seats,
            ],
        );
    }

    public function updateRegistration(RegistrationNoteRequest $request, int $registrationId, RegistrationService $registrations): JsonResponse
    {
        $registration = Registration::query()->with('event')->findOrFail($registrationId);

        try {
            $registrations->updateNote($registration, $request->user(), trim((string) $request->input('attendee_note', '')));
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception, 'Controlla i dati inseriti.');
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json('Nota aggiornata correttamente.');
    }

    public function cancelRegistration(Request $request, int $registrationId, RegistrationService $registrations): JsonResponse
    {
        $registration = Registration::query()->with('event')->findOrFail($registrationId);

        try {
            $registration = $registrations->cancelForUser($registration, $request->user());
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json('Iscrizione annullata con successo.', true, [
            'registration_id' => $registration->id,
            'status' => $registration->stato,
        ]);
    }

    public function submitFeedback(string $slug, FeedbackRequest $request, FeedbackService $feedbacks): JsonResponse
    {
        $event = Event::query()->where('slug', $slug)->firstOrFail();

        try {
            $feedback = $feedbacks->createOrUpdate(
                $event,
                $request->user(),
                (int) $request->input('rating'),
                (string) $request->input('comment', ''),
            );
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        } catch (AuthorizationException $exception) {
            return $this->jsonAuthorizationError($exception);
        }

        return ApiResponse::json('Feedback salvato con successo.', true, [
            'feedback_id' => $feedback->id,
            'rating' => $feedback->valutazione,
        ]);
    }

    private function payload(Request $request): array
    {
        if (str_starts_with((string) $request->header('Content-Type'), 'application/json')) {
            return $request->json()->all();
        }

        return $request->all();
    }
}

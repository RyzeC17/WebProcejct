<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Services\ApiResponse;
use App\Services\EventService;
use App\Services\FeedbackService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EventApiController extends Controller
{
    public function index(Request $request, EventService $events): JsonResponse|Response
    {
        $events->syncExpiredEventsStatuses();
        $items = Event::query()
            ->publicVisible()
            ->with('customFields.options')
            ->withRegistrationCounts()
            ->orderBy('inizio_il')
            ->get();

        if ($this->wantsXml($request)) {
            return ApiResponse::xmlForEvents($items);
        }

        return ApiResponse::json('Lista eventi recuperata con successo.', true, [
            'items' => $items->map(fn (Event $event) => ApiResponse::eventToArray($event))->all(),
        ]);
    }

    public function store(EventRequest $request, EventService $events): JsonResponse
    {
        try {
            $customFields = $events->normalizeCustomFieldPayload($request->input('custom_fields'));
            $event = $events->create($request->eventAttributes(), $request->user(), $customFields);
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        }

        return ApiResponse::json('Evento creato con successo.', true, [
            'item' => ApiResponse::eventToArray($event),
        ], [], 201);
    }

    public function show(int $eventId, Request $request, EventService $events): JsonResponse|Response
    {
        $event = Event::query()
            ->with('customFields.options')
            ->withRegistrationCounts()
            ->findOrFail($eventId);
        $events->syncEventStatus($event);
        $event->refresh()->load('customFields.options');
        $event->loadRegistrationCounts();

        if (! $request->user()?->hasRole('admin') && ! in_array($event->stato, [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED], true)) {
            abort(404);
        }

        if ($this->wantsXml($request)) {
            return ApiResponse::xmlForEvents([$event], 'eventDetail', 'event');
        }

        return ApiResponse::json('Dettaglio evento recuperato.', true, [
            'item' => ApiResponse::eventToArray($event),
        ]);
    }

    public function update(int $eventId, Request $request, EventService $events): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }
        if (! $request->user()->hasRole('admin')) {
            return ApiResponse::json('Permessi insufficienti.', false, [], [], 403);
        }

        $event = Event::query()->findOrFail($eventId);
        $payload = array_merge([
            'title' => $event->titolo,
            'description' => $event->descrizione,
            'venue_name' => $event->nome_luogo,
            'venue_address' => $event->indirizzo_luogo,
            'notes' => $event->note,
            'max_participants' => $event->max_partecipanti,
            'price' => $event->prezzo,
            'start_datetime' => $event->inizio_il,
            'end_datetime' => $event->fine_il,
            'registration_deadline' => $event->scadenza_iscrizioni,
            'event_type' => $event->tipo_evento,
            'status' => $event->stato,
        ], $request->all());

        $validator = Validator::make($payload, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'max_participants' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'registration_deadline' => ['required', 'date', 'before_or_equal:start_datetime'],
            'event_type' => ['required', Rule::in(array_keys(Event::EVENT_TYPES))],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
        ]);

        if ($validator->fails()) {
            return ApiResponse::json('Controlla i dati inviati.', false, [], $validator->errors()->toArray(), 400);
        }

        try {
            $customFieldsProvided = $request->has('custom_fields');
            $customFields = $events->normalizeCustomFieldPayload($request->input('custom_fields'));
            $validated = $validator->validated();
            $updated = $events->update($event, [
                'titolo' => $validated['title'],
                'descrizione' => $validated['description'],
                'nome_luogo' => $validated['venue_name'],
                'indirizzo_luogo' => $validated['venue_address'],
                'note' => $validated['notes'] ?? '',
                'max_partecipanti' => $validated['max_participants'],
                'prezzo' => $validated['price'],
                'inizio_il' => $validated['start_datetime'],
                'fine_il' => $validated['end_datetime'],
                'scadenza_iscrizioni' => $validated['registration_deadline'],
                'tipo_evento' => $validated['event_type'],
                'stato' => $validated['status'],
            ], $customFields, $customFieldsProvided, $request->user());
        } catch (ValidationException $exception) {
            return $this->jsonValidationError($exception);
        }

        return ApiResponse::json('Evento aggiornato con successo.', true, [
            'item' => ApiResponse::eventToArray($updated),
        ]);
    }

    public function destroy(int $eventId, Request $request, EventService $events): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }
        if (! $request->user()->hasRole('admin')) {
            return ApiResponse::json('Permessi insufficienti.', false, [], [], 403);
        }

        $events->delete(Event::query()->findOrFail($eventId));

        return ApiResponse::json('Evento eliminato con successo.');
    }

    public function calendar(Request $request, EventService $events): JsonResponse
    {
        $events->syncExpiredEventsStatuses();
        $viewMode = $request->query('view', 'month');
        $date = Carbon::today();
        if ($request->query('date')) {
            try {
                $date = Carbon::parse((string) $request->query('date'));
            } catch (\Throwable) {
                $date = Carbon::today();
            }
        }

        if ($viewMode === 'week') {
            $rangeStart = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $rangeEnd = $rangeStart->copy()->addDays(7);
        } else {
            $rangeStart = $date->copy()->startOfMonth()->startOfDay();
            $rangeEnd = $date->copy()->endOfMonth()->addDay()->startOfDay();
        }

        $items = Event::query()
            ->where('stato', Event::STATUS_PUBLISHED)
            ->whereDate('inizio_il', '<', $rangeEnd->toDateString())
            ->whereDate('fine_il', '>=', $rangeStart->toDateString())
            ->orderBy('inizio_il')
            ->get();

        return ApiResponse::json('Calendario recuperato.', true, [
            'items' => $items->map(fn (Event $event) => ApiResponse::calendarEventToArray($event))->all(),
        ]);
    }

    public function history(int $eventId, Request $request): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }
        if (! $request->user()->hasRole('admin')) {
            return ApiResponse::json('Permessi insufficienti.', false, [], [], 403);
        }

        $event = Event::query()->with('changeLogs.actor')->findOrFail($eventId);

        return ApiResponse::json('Storico recuperato.', true, [
            'items' => $event->changeLogs->map(fn ($log) => ApiResponse::changelogToArray($log))->all(),
        ]);
    }

    public function feedback(int $eventId, Request $request, EventService $events, FeedbackService $feedbacks): JsonResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $events->syncEventStatus($event);
        $event->refresh();

        if (! in_array($event->stato, [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED], true) && ! $request->user()?->hasRole('admin')) {
            abort(404);
        }

        return ApiResponse::json('Feedback recuperati.', true, [
            'summary' => ApiResponse::feedbackSummaryToArray($feedbacks->summary($event)),
            'items' => $event->feedbacks()->with('user')->get()->map(fn ($feedback) => ApiResponse::feedbackToArray($feedback))->all(),
        ]);
    }

    public function submitFeedback(int $eventId, Request $request, FeedbackService $feedbacks): JsonResponse
    {
        if (! $request->user()) {
            return ApiResponse::json('Autenticazione richiesta.', false, [], [], 401);
        }

        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::json('Controlla i dati inviati.', false, [], $validator->errors()->toArray(), 400);
        }

        $event = Event::query()->findOrFail($eventId);
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

        return ApiResponse::json('Feedback salvato.', true, [
            'item' => ApiResponse::feedbackToArray($feedback),
        ], [], 201);
    }

    private function wantsXml(Request $request): bool
    {
        return $request->query('format') === 'xml' || str_contains((string) $request->header('Accept'), 'application/xml');
    }
}

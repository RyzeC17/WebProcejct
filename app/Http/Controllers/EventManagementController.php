<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\Registration;
use App\Services\ApiResponse;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventManagementController extends Controller
{
    public function index(Request $request, EventService $events): View
    {
        $events->syncExpiredEventsStatuses();
        $query = Event::query()
            ->with('createdBy')
            ->withRegistrationCounts()
            ->orderBy('inizio_il')
            ->orderBy('titolo');
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));

        if ($search !== '') {
            $query->where(fn ($inner) => $inner
                ->where('titolo', 'like', "%{$search}%")
                ->orWhere('nome_luogo', 'like', "%{$search}%"));
        }

        if ($status !== '') {
            $query->where('stato', $status);
        }

        return view('events.manage_list', [
            'events' => $query->get(),
            'statusChoices' => Event::STATUSES,
            'filters' => compact('search', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('events.manage_form', [
            'event' => new Event(['stato' => Event::STATUS_DRAFT, 'prezzo' => 0]),
            'eventTypes' => Event::EVENT_TYPES,
            'statusChoices' => Event::STATUSES,
            'fieldTypes' => EventCustomField::FIELD_TYPES,
            'customFields' => collect(),
            'canEditCustomFields' => true,
        ]);
    }

    public function store(EventRequest $request, EventService $events): RedirectResponse
    {
        try {
            $customFields = $events->customFieldsFromForm($request->all());
            $events->create($request->eventAttributes(), $request->user(), $customFields);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('events.manage-list')->with('success', 'Evento creato con successo.');
    }

    public function edit(int $eventId): View
    {
        $event = Event::query()->with('customFields.options')->findOrFail($eventId);

        return view('events.manage_form', [
            'event' => $event,
            'eventTypes' => Event::EVENT_TYPES,
            'statusChoices' => Event::STATUSES,
            'fieldTypes' => EventCustomField::FIELD_TYPES,
            'customFields' => $event->customFields,
            'canEditCustomFields' => $event->can_configure_custom_fields,
        ]);
    }

    public function update(EventRequest $request, int $eventId, EventService $events): RedirectResponse
    {
        $event = Event::query()->with('customFields.options')->findOrFail($eventId);
        $customFieldsProvided = $request->has('custom_fields');

        try {
            $customFields = $customFieldsProvided && $event->can_configure_custom_fields
                ? $events->customFieldsFromForm($request->all())
                : null;
            $events->update($event, $request->eventAttributes(), $customFields, $customFieldsProvided, $request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('events.manage-list')->with('success', 'Evento aggiornato con successo.');
    }

    public function destroy(int $eventId, EventService $events): RedirectResponse
    {
        $event = Event::query()->findOrFail($eventId);
        $events->delete($event);

        return redirect()->route('events.manage-list')->with('success', 'Evento eliminato con successo.');
    }

    public function changeStatus(int $eventId, Request $request, EventService $events): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
        ]);

        if ($validator->fails()) {
            return ApiResponse::json('Stato non valido.', false, [], $validator->errors()->toArray(), 400);
        }

        $event = Event::query()->findOrFail($eventId);
        $event = $events->changeStatus($event, (string) $request->input('status'), $request->user());

        return ApiResponse::json('Stato aggiornato.', true, ['status' => $event->stato]);
    }

    public function registrants(int $eventId): View
    {
        $event = Event::query()->with('customFields.options')->findOrFail($eventId);
        [$registrations, $customFields, $aggregateAnswers] = $this->registrationOverview($event);

        return view('events.registrants', [
            'event' => $event,
            'registrations' => $registrations,
            'customFields' => $customFields,
            'aggregateAnswers' => $aggregateAnswers,
            'registrantColumnCount' => 5 + $customFields->count(),
        ]);
    }

    public function history(int $eventId): View
    {
        $event = Event::query()->with('changeLogs.actor')->findOrFail($eventId);

        return view('events.history', [
            'event' => $event,
            'changeLogs' => $event->changeLogs,
        ]);
    }

    private function registrationOverview(Event $event): array
    {
        $customFields = $event->customFields()->with('options')->get();
        $registrations = Registration::query()
            ->where('evento_id', $event->id)
            ->with('user', 'customAnswers.field', 'customAnswers.selectedOption')
            ->orderByDesc('creato_il')
            ->get();

        $registrations->each(function (Registration $registration) use ($customFields) {
            $answers = $registration->customAnswers->keyBy('campo_id');
            $registration->custom_answer_pairs = $customFields
                ->map(fn ($field) => [
                    'label' => $field->etichetta,
                    'value' => $answers->get($field->id)?->display_value ?: '-',
                ])
                ->all();
        });

        $aggregateAnswers = $customFields->map(function (EventCustomField $field) use ($registrations) {
            $fieldAnswers = $registrations
                ->flatMap(fn (Registration $registration) => $registration->customAnswers->where('campo_id', $field->id)
                    ->map(fn ($answer) => ['registration' => $registration, 'answer' => $answer]))
                ->values();

            $item = [
                'field' => $field,
                'field_type' => $field->tipo_campo,
                'count' => $fieldAnswers->count(),
            ];

            if ($field->tipo_campo === EventCustomField::TYPE_TEXT) {
                $item['values'] = $fieldAnswers
                    ->map(fn ($pair) => [
                        'user_label' => $pair['registration']->user->display_name,
                        'value' => $pair['answer']->valore_testo,
                    ])
                    ->all();
            } elseif ($field->tipo_campo === EventCustomField::TYPE_NUMBER) {
                $values = $fieldAnswers->map(fn ($pair) => (float) $pair['answer']->valore_numero);
                $item['min'] = $values->isNotEmpty() ? $values->min() : null;
                $item['max'] = $values->isNotEmpty() ? $values->max() : null;
                $item['avg'] = $values->isNotEmpty() ? $values->avg() : null;
            } else {
                $item['counts'] = $fieldAnswers
                    ->map(fn ($pair) => $pair['answer']->display_value)
                    ->countBy()
                    ->all();
            }

            return $item;
        });

        return [$registrations, $customFields, $aggregateAnswers];
    }
}

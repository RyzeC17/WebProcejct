<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCustomField;
use App\Models\FieldOption;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\RegistrationCustomAnswer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function __construct(
        private readonly EventService $events,
        private readonly NotificationService $notifications,
    ) {
    }

    public function validateRegistrationRules(Event $event, User $user): void
    {
        $this->events->syncEventStatus($event);
        $event->refresh();

        if ($event->status !== Event::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['event' => "L'evento non e aperto alle iscrizioni."]);
        }

        if ($event->registration_deadline?->lessThan(Carbon::now())) {
            throw ValidationException::withMessages(['event' => 'Il termine per le iscrizioni e scaduto.']);
        }

        if ($event->start_datetime?->lessThanOrEqualTo(Carbon::now())) {
            throw ValidationException::withMessages(['event' => "L'evento e gia iniziato o concluso."]);
        }

        $alreadyRegistered = Registration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED])
            ->exists();

        if ($alreadyRegistered) {
            throw ValidationException::withMessages(['event' => 'Risulti gia registrato o in lista d\'attesa per questo evento.']);
        }
    }

    public function customAnswersFromPayload(Event $event, array $payload): array
    {
        $event->loadMissing('customFields.options');
        $definitions = [];

        foreach ($event->customFields as $field) {
            $name = 'custom_field_'.$field->id;
            $raw = $payload[$name] ?? null;
            if ($field->is_required && ($raw === null || $raw === '')) {
                throw ValidationException::withMessages([$name => 'Questo campo e obbligatorio.']);
            }

            if ($raw === null || $raw === '') {
                continue;
            }

            if ($field->field_type === EventCustomField::TYPE_TEXT) {
                $definitions[] = ['field' => $field, 'text_value' => trim((string) $raw)];
            } elseif ($field->field_type === EventCustomField::TYPE_NUMBER) {
                if (! is_numeric($raw)) {
                    throw ValidationException::withMessages([$name => 'Il valore numerico non e valido.']);
                }
                $definitions[] = ['field' => $field, 'number_value' => $raw];
            } elseif ($field->field_type === EventCustomField::TYPE_BOOLEAN) {
                $value = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    throw ValidationException::withMessages([$name => 'Il valore booleano non e valido.']);
                }
                $definitions[] = ['field' => $field, 'boolean_value' => $value];
            } elseif ($field->field_type === EventCustomField::TYPE_SELECT) {
                $option = FieldOption::query()->where('field_id', $field->id)->whereKey($raw)->first();
                if (! $option) {
                    throw ValidationException::withMessages([$name => "L'opzione selezionata non appartiene al campo richiesto."]);
                }
                $definitions[] = ['field' => $field, 'selected_option' => $option];
            }
        }

        return $definitions;
    }

    public function createOrReactivate(Event $event, User $user, string $attendeeNote = '', array $customAnswers = []): Registration
    {
        $this->validateRegistrationRules($event, $user);

        return DB::transaction(function () use ($event, $user, $attendeeNote, $customAnswers) {
            /** @var Event $locked */
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->validateRegistrationRules($locked, $user);

            $hadAvailableSeats = $locked->remaining_seats > 0;
            $targetStatus = $hadAvailableSeats ? Registration::STATUS_ACTIVE : Registration::STATUS_WAITLISTED;
            $registration = Registration::query()
                ->where('event_id', $locked->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($registration) {
                $registration->forceFill([
                    'status' => $targetStatus,
                    'attendee_note' => $attendeeNote,
                    'cancelled_at' => null,
                    'promoted_at' => null,
                ])->save();
            } else {
                $registration = Registration::query()->create([
                    'event_id' => $locked->id,
                    'user_id' => $user->id,
                    'status' => $targetStatus,
                    'attendee_note' => $attendeeNote,
                ]);
            }

            $this->saveCustomAnswers($registration, $customAnswers);

            if ($registration->status === Registration::STATUS_ACTIVE) {
                $this->notifications->create(
                    $user,
                    Notification::TYPE_REGISTRATION_CONFIRMED,
                    "La tua iscrizione all'evento '{$locked->title}' e stata confermata.",
                    $locked,
                    $registration,
                );
                if ($hadAvailableSeats && $locked->refresh()->remaining_seats === 0) {
                    $this->notifications->create(
                        $locked->createdBy,
                        Notification::TYPE_EVENT_FULL,
                        "L'evento '{$locked->title}' ha esaurito i posti disponibili.",
                        $locked,
                    );
                }
            }

            return $registration->refresh();
        });
    }

    public function updateNote(Registration $registration, User $user, string $note): Registration
    {
        if ($registration->user_id !== $user->id) {
            throw new AuthorizationException('Non puoi modificare questa iscrizione.');
        }

        if (! in_array($registration->status, [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED], true)) {
            throw ValidationException::withMessages(['attendee_note' => "L'iscrizione non e piu attiva."]);
        }

        if ($registration->event->registration_deadline?->lessThan(Carbon::now())) {
            throw ValidationException::withMessages(['attendee_note' => "Non e piu possibile modificare l'iscrizione."]);
        }

        $registration->forceFill(['attendee_note' => $note])->save();

        return $registration->refresh();
    }

    public function cancelForUser(Registration $registration, User $user): Registration
    {
        return DB::transaction(function () use ($registration, $user) {
            /** @var Registration $locked */
            $locked = Registration::query()->with('event')->lockForUpdate()->findOrFail($registration->id);
            if ($locked->user_id !== $user->id && ! $user->is_staff) {
                throw new AuthorizationException('Non puoi annullare questa iscrizione.');
            }
            if ($locked->status === Registration::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['registration' => "L'iscrizione e gia annullata."]);
            }
            if (! $user->is_staff && $locked->event->registration_deadline?->lessThan(Carbon::now())) {
                throw ValidationException::withMessages(['registration' => 'Il termine utile per annullare e scaduto.']);
            }

            $shouldPromote = $locked->status === Registration::STATUS_ACTIVE;
            $locked->forceFill([
                'status' => Registration::STATUS_CANCELLED,
                'cancelled_at' => Carbon::now(),
            ])->save();

            if ($shouldPromote) {
                $this->promoteNextWaitlisted($locked->event);
            }

            return $locked->refresh();
        });
    }

    private function saveCustomAnswers(Registration $registration, array $customAnswers): void
    {
        $seen = [];
        foreach ($customAnswers as $definition) {
            /** @var EventCustomField $field */
            $field = $definition['field'];
            $payload = [
                'text_value' => null,
                'number_value' => null,
                'boolean_value' => null,
                'selected_option_id' => null,
            ];

            if (array_key_exists('text_value', $definition)) {
                $payload['text_value'] = $definition['text_value'];
            } elseif (array_key_exists('number_value', $definition)) {
                $payload['number_value'] = $definition['number_value'];
            } elseif (array_key_exists('boolean_value', $definition)) {
                $payload['boolean_value'] = $definition['boolean_value'];
            } elseif (array_key_exists('selected_option', $definition)) {
                $payload['selected_option_id'] = $definition['selected_option']->id;
            }

            RegistrationCustomAnswer::query()->updateOrCreate(
                [
                    'registration_id' => $registration->id,
                    'field_id' => $field->id,
                ],
                $payload,
            );
            $seen[] = $field->id;
        }

        $registration->customAnswers()
            ->when($seen !== [], fn ($query) => $query->whereNotIn('field_id', $seen))
            ->when($seen === [], fn ($query) => $query)
            ->delete();
    }

    private function promoteNextWaitlisted(Event $event): ?Registration
    {
        /** @var Event $lockedEvent */
        $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
        if ($lockedEvent->remaining_seats <= 0) {
            return null;
        }

        /** @var Registration|null $promoted */
        $promoted = Registration::query()
            ->where('event_id', $lockedEvent->id)
            ->where('status', Registration::STATUS_WAITLISTED)
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $promoted) {
            return null;
        }

        $promoted->forceFill([
            'status' => Registration::STATUS_ACTIVE,
            'promoted_at' => Carbon::now(),
        ])->save();

        $this->notifications->create(
            $promoted->user,
            Notification::TYPE_WAITLIST_PROMOTED,
            "Si e liberato un posto per l'evento '{$lockedEvent->title}': la tua adesione e ora confermata.",
            $lockedEvent,
            $promoted,
        );

        return $promoted->refresh();
    }
}

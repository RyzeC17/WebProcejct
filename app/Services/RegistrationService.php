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

        if ($event->stato !== Event::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['event' => "L'evento non e aperto alle iscrizioni."]);
        }

        if ($event->scadenza_iscrizioni?->lessThan(Carbon::now())) {
            throw ValidationException::withMessages(['event' => 'Il termine per le iscrizioni e scaduto.']);
        }

        if ($event->inizio_il?->lessThanOrEqualTo(Carbon::now())) {
            throw ValidationException::withMessages(['event' => "L'evento e gia iniziato o concluso."]);
        }

        $alreadyRegistered = Registration::query()
            ->where('evento_id', $event->id)
            ->where('utente_id', $user->id)
            ->whereIn('stato', [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED])
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
            if ($field->obbligatorio && ($raw === null || $raw === '')) {
                throw ValidationException::withMessages([$name => 'Questo campo e obbligatorio.']);
            }

            if ($raw === null || $raw === '') {
                continue;
            }

            if ($field->tipo_campo === EventCustomField::TYPE_TEXT) {
                $definitions[] = ['field' => $field, 'valore_testo' => trim((string) $raw)];
            } elseif ($field->tipo_campo === EventCustomField::TYPE_NUMBER) {
                if (! is_numeric($raw)) {
                    throw ValidationException::withMessages([$name => 'Il valore numerico non e valido.']);
                }
                $definitions[] = ['field' => $field, 'valore_numero' => $raw];
            } elseif ($field->tipo_campo === EventCustomField::TYPE_BOOLEAN) {
                $value = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    throw ValidationException::withMessages([$name => 'Il valore booleano non e valido.']);
                }
                $definitions[] = ['field' => $field, 'valore_booleano' => $value];
            } elseif ($field->tipo_campo === EventCustomField::TYPE_SELECT) {
                $option = FieldOption::query()->where('campo_id', $field->id)->whereKey($raw)->first();
                if (! $option) {
                    throw ValidationException::withMessages([$name => "L'opzione selezionata non appartiene al campo richiesto."]);
                }
                $definitions[] = ['field' => $field, 'opzione_selezionata' => $option];
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
                ->where('evento_id', $locked->id)
                ->where('utente_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($registration) {
                $registration->forceFill([
                    'stato' => $targetStatus,
                    'nota_partecipante' => $attendeeNote,
                    'annullata_il' => null,
                    'promossa_il' => null,
                ])->save();
            } else {
                $registration = Registration::query()->create([
                    'evento_id' => $locked->id,
                    'utente_id' => $user->id,
                    'stato' => $targetStatus,
                    'nota_partecipante' => $attendeeNote,
                ]);
            }

            $this->saveCustomAnswers($registration, $customAnswers);

            if ($registration->stato === Registration::STATUS_ACTIVE) {
                $this->notifications->create(
                    $user,
                    Notification::TYPE_REGISTRATION_CONFIRMED,
                    "La tua iscrizione all'evento '{$locked->titolo}' e stata confermata.",
                    $locked,
                    $registration,
                );
                if ($hadAvailableSeats && $locked->refresh()->remaining_seats === 0) {
                    $this->notifications->create(
                        $locked->createdBy,
                        Notification::TYPE_EVENT_FULL,
                        "L'evento '{$locked->titolo}' ha esaurito i posti disponibili.",
                        $locked,
                    );
                }
            }

            return $registration->refresh();
        });
    }

    public function updateNote(Registration $registration, User $user, string $note): Registration
    {
        if ($registration->utente_id !== $user->id) {
            throw new AuthorizationException('Non puoi modificare questa iscrizione.');
        }

        if (! in_array($registration->stato, [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED], true)) {
            throw ValidationException::withMessages(['attendee_note' => "L'iscrizione non e piu attiva."]);
        }

        if ($registration->event->scadenza_iscrizioni?->lessThan(Carbon::now())) {
            throw ValidationException::withMessages(['attendee_note' => "Non e piu possibile modificare l'iscrizione."]);
        }

        $registration->forceFill(['nota_partecipante' => $note])->save();

        return $registration->refresh();
    }

    public function cancelForUser(Registration $registration, User $user): Registration
    {
        return DB::transaction(function () use ($registration, $user) {
            /** @var Registration $locked */
            $locked = Registration::query()->with('event')->lockForUpdate()->findOrFail($registration->id);
            if ($locked->utente_id !== $user->id && ! $user->hasRole('admin')) {
                throw new AuthorizationException('Non puoi annullare questa iscrizione.');
            }
            if ($locked->stato === Registration::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['registration' => "L'iscrizione e gia annullata."]);
            }
            if (! $user->hasRole('admin') && $locked->event->scadenza_iscrizioni?->lessThan(Carbon::now())) {
                throw ValidationException::withMessages(['registration' => 'Il termine utile per annullare e scaduto.']);
            }

            $shouldPromote = $locked->stato === Registration::STATUS_ACTIVE;
            $locked->forceFill([
                'stato' => Registration::STATUS_CANCELLED,
                'annullata_il' => Carbon::now(),
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
                'valore_testo' => null,
                'valore_numero' => null,
                'valore_booleano' => null,
                'opzione_selezionata_id' => null,
            ];

            if (array_key_exists('valore_testo', $definition)) {
                $payload['valore_testo'] = $definition['valore_testo'];
            } elseif (array_key_exists('valore_numero', $definition)) {
                $payload['valore_numero'] = $definition['valore_numero'];
            } elseif (array_key_exists('valore_booleano', $definition)) {
                $payload['valore_booleano'] = $definition['valore_booleano'];
            } elseif (array_key_exists('opzione_selezionata', $definition)) {
                $payload['opzione_selezionata_id'] = $definition['opzione_selezionata']->id;
            }

            RegistrationCustomAnswer::query()->updateOrCreate(
                [
                    'iscrizione_id' => $registration->id,
                    'campo_id' => $field->id,
                ],
                $payload,
            );
            $seen[] = $field->id;
        }

        $registration->customAnswers()
            ->when($seen !== [], fn ($query) => $query->whereNotIn('campo_id', $seen))
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
            ->where('evento_id', $lockedEvent->id)
            ->where('stato', Registration::STATUS_WAITLISTED)
            ->with('user')
            ->orderBy('creato_il')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (! $promoted) {
            return null;
        }

        $promoted->forceFill([
            'stato' => Registration::STATUS_ACTIVE,
            'promossa_il' => Carbon::now(),
        ])->save();

        $this->notifications->create(
            $promoted->user,
            Notification::TYPE_WAITLIST_PROMOTED,
            "Si e liberato un posto per l'evento '{$lockedEvent->titolo}': la tua adesione e ora confermata.",
            $lockedEvent,
            $promoted,
        );

        return $promoted->refresh();
    }
}

<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChangeLog;
use App\Models\EventCustomField;
use App\Models\FieldOption;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventService
{
    private const TRACKED_EVENT_NOTIFICATION_FIELDS = ['nome_luogo', 'indirizzo_luogo', 'inizio_il', 'fine_il'];

    private const TRACKED_CHANGELOG_FIELDS = [
        'titolo',
        'descrizione',
        'nome_luogo',
        'indirizzo_luogo',
        'note',
        'max_partecipanti',
        'prezzo',
        'inizio_il',
        'fine_il',
        'scadenza_iscrizioni',
        'tipo_evento',
        'stato',
    ];

    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function syncExpiredEventsStatuses(): void
    {
        Event::query()
            ->whereIn('stato', [Event::STATUS_PUBLISHED, Event::STATUS_CLOSED])
            ->where('fine_il', '<', Carbon::now())
            ->update(['stato' => Event::STATUS_COMPLETED, 'aggiornato_il' => Carbon::now()]);
    }

    public function syncEventStatus(Event $event): Event
    {
        if (in_array($event->stato, [Event::STATUS_PUBLISHED, Event::STATUS_CLOSED], true)
            && $event->fine_il?->lessThan(Carbon::now())) {
            $event->forceFill(['stato' => Event::STATUS_COMPLETED])->save();
        }

        return $event->refresh();
    }

    public function create(array $attributes, User $createdBy, ?array $customFields = null): Event
    {
        return DB::transaction(function () use ($attributes, $createdBy, $customFields) {
            $attributes['note'] = $attributes['note'] ?? '';
            $event = Event::query()->create(array_merge($attributes, ['creato_da_id' => $createdBy->id]));
            if ($customFields !== null) {
                $this->syncCustomFieldsFromPayload($event, $customFields);
            }

            return $event->refresh();
        });
    }

    public function update(Event $event, array $attributes, ?array $customFields = null, bool $customFieldsProvided = false, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($event, $attributes, $customFields, $customFieldsProvided, $actor) {
            $attributes['note'] = $attributes['note'] ?? '';
            /** @var Event $locked */
            $locked = Event::query()->lockForUpdate()->with('registrations')->findOrFail($event->id);
            $notificationSnapshot = $this->captureNotificationSnapshot($locked);
            $changelogSnapshot = $this->captureChangelogSnapshot($locked);
            $initialStatus = $locked->stato;

            if ($customFieldsProvided && ! $locked->can_configure_custom_fields) {
                throw ValidationException::withMessages([
                    'custom_fields' => 'I campi aggiuntivi possono essere modificati solo in bozza o prima della prima iscrizione attiva.',
                ]);
            }

            $locked->fill($attributes);
            $locked->save();

            if ($customFields !== null) {
                $this->syncCustomFieldsFromPayload($locked, $customFields);
            }

            $this->maybeNotifyEventChanges($locked->refresh(), $initialStatus, $notificationSnapshot);
            $this->createChangelogEntry($locked, $changelogSnapshot, $actor);

            return $locked->refresh();
        });
    }

    public function delete(Event $event): Event
    {
        return DB::transaction(function () use ($event) {
            /** @var Event $locked */
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            Notification::query()
                ->where('evento_id', $locked->id)
                ->orWhereIn('iscrizione_id', Registration::query()->where('evento_id', $locked->id)->select('id'))
                ->delete();
            $locked->delete();

            return $locked;
        });
    }

    public function changeStatus(Event $event, string $newStatus, ?User $actor = null): Event
    {
        return DB::transaction(function () use ($event, $newStatus, $actor) {
            /** @var Event $locked */
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $initialStatus = $locked->stato;
            if ($initialStatus === $newStatus) {
                return $locked;
            }

            $snapshot = $this->captureChangelogSnapshot($locked);
            $locked->forceFill(['stato' => $newStatus])->save();

            if ($newStatus === Event::STATUS_CANCELLED && $initialStatus !== Event::STATUS_CANCELLED) {
                $this->notifyEventCancelled($locked);
            }
            $this->createChangelogEntry($locked, $snapshot, $actor);

            return $locked->refresh();
        });
    }

    public function normalizeCustomFieldPayload(mixed $customFields): ?array
    {
        if ($customFields === null) {
            return null;
        }

        if (! is_array($customFields)) {
            throw ValidationException::withMessages(['custom_fields' => 'Il blocco custom_fields deve essere una lista.']);
        }

        $normalized = [];
        $usedOrders = [];
        $allowedTypes = array_keys(EventCustomField::FIELD_TYPES);
        foreach (array_values($customFields) as $index => $item) {
            if (! is_array($item)) {
                throw ValidationException::withMessages(['custom_fields' => 'Ogni campo personalizzato deve essere un oggetto JSON.']);
            }

            $label = trim((string) ($item['label'] ?? ''));
            $fieldType = $item['field_type'] ?? null;
            if ($label === '') {
                throw ValidationException::withMessages(['custom_fields' => "Ogni campo personalizzato deve avere un'etichetta."]);
            }
            if (! in_array($fieldType, $allowedTypes, true)) {
                throw ValidationException::withMessages(['custom_fields' => 'Il tipo di campo personalizzato non e valido.']);
            }

            $displayOrder = (int) ($item['display_order'] ?? ($index + 1));
            if ($displayOrder <= 0) {
                throw ValidationException::withMessages(['custom_fields' => "L'ordine di visualizzazione deve essere maggiore di zero."]);
            }
            if (in_array($displayOrder, $usedOrders, true)) {
                throw ValidationException::withMessages(['custom_fields' => "L'ordine di visualizzazione deve essere univoco per evento."]);
            }
            $usedOrders[] = $displayOrder;

            $isRequired = filter_var($item['is_required'] ?? false, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($isRequired === null) {
                throw ValidationException::withMessages(['custom_fields' => 'Il flag is_required deve essere booleano.']);
            }

            $options = $item['options'] ?? [];
            if ($fieldType === EventCustomField::TYPE_SELECT) {
                if (! is_array($options)) {
                    throw ValidationException::withMessages(['custom_fields' => 'Le opzioni del campo select devono essere una lista.']);
                }
                $options = array_values(array_filter(array_map(fn ($value) => trim((string) $value), $options)));
                if ($options === []) {
                    throw ValidationException::withMessages(['custom_fields' => 'I campi select richiedono almeno un\'opzione.']);
                }
                if (count($options) !== count(array_unique($options))) {
                    throw ValidationException::withMessages(['custom_fields' => 'Le opzioni di un campo select devono essere univoche.']);
                }
            } else {
                $options = [];
            }

            $normalized[] = [
                'label' => $label,
                'field_type' => $fieldType,
                'is_required' => $isRequired,
                'display_order' => $displayOrder,
                'options' => $options,
            ];
        }

        usort($normalized, fn ($a, $b) => [$a['display_order'], $a['label']] <=> [$b['display_order'], $b['label']]);

        return $normalized;
    }

    public function customFieldsFromForm(array $input): array
    {
        $rows = $input['custom_fields'] ?? [];
        $normalized = [];
        foreach ($rows as $row) {
            if (! empty($row['DELETE'])) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $fieldType = $row['field_type'] ?? '';
            $optionsText = trim((string) ($row['options_text'] ?? ''));
            $isRequired = isset($row['is_required']) && $row['is_required'];
            $hasExistingId = trim((string) ($row['id'] ?? '')) !== '';

            if (
                ! $hasExistingId
                && $label === ''
                && ! $isRequired
                && $optionsText === ''
                && ($fieldType === '' || $fieldType === EventCustomField::TYPE_TEXT)
            ) {
                continue;
            }

            $options = $fieldType === EventCustomField::TYPE_SELECT
                ? preg_split('/\R/u', (string) ($row['options_text'] ?? ''), -1, PREG_SPLIT_NO_EMPTY)
                : [];

            $normalized[] = [
                'label' => $label,
                'field_type' => $fieldType,
                'is_required' => $isRequired,
                'display_order' => (int) ($row['display_order'] ?? 0),
                'options' => $options,
            ];
        }

        return $this->normalizeCustomFieldPayload($normalized) ?? [];
    }

    private function syncCustomFieldsFromPayload(Event $event, array $configs): void
    {
        if (! $event->can_configure_custom_fields) {
            throw ValidationException::withMessages([
                'custom_fields' => 'I campi aggiuntivi possono essere modificati solo in bozza o prima della prima iscrizione attiva.',
            ]);
        }

        $event->customFields()->delete();
        foreach ($configs as $config) {
            $field = EventCustomField::query()->create([
                'evento_id' => $event->id,
                'etichetta' => trim($config['label']),
                'tipo_campo' => $config['field_type'],
                'obbligatorio' => (bool) $config['is_required'],
                'ordine_visualizzazione' => (int) $config['display_order'],
            ]);

            foreach ($config['options'] as $index => $value) {
                FieldOption::query()->create([
                    'campo_id' => $field->id,
                    'valore' => trim($value),
                    'ordine_visualizzazione' => $index + 1,
                ]);
            }
        }
    }

    private function captureNotificationSnapshot(Event $event): array
    {
        return collect(self::TRACKED_EVENT_NOTIFICATION_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $this->normalizeChangelogValue($event->{$field})])
            ->all();
    }

    private function captureChangelogSnapshot(Event $event): array
    {
        return collect(self::TRACKED_CHANGELOG_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $this->normalizeChangelogValue($event->{$field})])
            ->all();
    }

    private function normalizeChangelogValue(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        return $value;
    }

    private function createChangelogEntry(Event $event, array $before, ?User $actor = null): ?EventChangeLog
    {
        $after = $this->captureChangelogSnapshot($event->refresh());
        $changed = [];
        foreach (self::TRACKED_CHANGELOG_FIELDS as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[$field] = [
                    'old' => $before[$field] ?? null,
                    'new' => $after[$field] ?? null,
                ];
            }
        }

        if ($changed === []) {
            return null;
        }

        return EventChangeLog::query()->create([
            'evento_id' => $event->id,
            'autore_id' => $actor?->id,
            'campi_modificati' => $changed,
        ]);
    }

    private function maybeNotifyEventChanges(Event $event, string $initialStatus, array $snapshot): void
    {
        if ($event->stato === Event::STATUS_CANCELLED) {
            if ($initialStatus !== Event::STATUS_CANCELLED) {
                $this->notifyEventCancelled($event);
            }
            return;
        }

        if ($initialStatus === Event::STATUS_CANCELLED) {
            return;
        }

        foreach (self::TRACKED_EVENT_NOTIFICATION_FIELDS as $field) {
            if (($snapshot[$field] ?? null) !== $this->normalizeChangelogValue($event->{$field})) {
                $this->notifyEventUpdated($event);
                return;
            }
        }
    }

    private function notifyEventUpdated(Event $event): void
    {
        foreach ($event->registrations()->where('stato', Registration::STATUS_ACTIVE)->with('user')->get() as $registration) {
            $this->notifications->create(
                $registration->user,
                Notification::TYPE_EVENT_UPDATED,
                "L'evento '{$event->titolo}' e stato aggiornato. Controlla luogo, data e orario.",
                $event,
                $registration,
            );
        }
    }

    private function notifyEventCancelled(Event $event): void
    {
        foreach ($event->registrations()->whereIn('stato', [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED])->with('user')->get() as $registration) {
            $this->notifications->create(
                $registration->user,
                Notification::TYPE_EVENT_CANCELLED,
                "L'evento '{$event->titolo}' e stato annullato.",
                $event,
                $registration,
            );
        }
    }
}

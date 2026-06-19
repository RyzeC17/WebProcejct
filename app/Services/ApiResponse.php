<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventChangeLog;
use App\Models\EventCustomField;
use App\Models\EventFeedback;
use App\Models\Registration;
use App\Models\RegistrationCustomAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ApiResponse
{
    public static function json(string $message, bool $success = true, array $data = [], array $errors = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $status);
    }

    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->timezone(config('app.timezone'))->toIso8601String();
        }

        return $value;
    }

    public static function customFieldToArray(EventCustomField $field): array
    {
        return [
            'id' => $field->id,
            'label' => $field->etichetta,
            'field_type' => $field->tipo_campo,
            'is_required' => (bool) $field->obbligatorio,
            'display_order' => $field->ordine_visualizzazione,
            'options' => $field->tipo_campo === EventCustomField::TYPE_SELECT
                ? $field->options->map(fn ($option) => [
                    'id' => $option->id,
                    'value' => $option->valore,
                    'display_order' => $option->ordine_visualizzazione,
                ])->values()->all()
                : [],
        ];
    }

    public static function eventToArray(Event $event): array
    {
        $event->loadMissing('customFields.options');

        return [
            'id' => $event->id,
            'title' => $event->titolo,
            'slug' => $event->slug,
            'description' => $event->descrizione,
            'venue_name' => $event->nome_luogo,
            'venue_address' => $event->indirizzo_luogo,
            'notes' => $event->note,
            'max_participants' => $event->max_partecipanti,
            'price' => (string) $event->prezzo,
            'start_datetime' => self::normalize($event->inizio_il),
            'end_datetime' => self::normalize($event->fine_il),
            'registration_deadline' => self::normalize($event->scadenza_iscrizioni),
            'event_type' => $event->tipo_evento,
            'status' => $event->stato,
            'operational_state' => $event->operational_state,
            'remaining_seats' => $event->remaining_seats,
            'accepts_new_requests' => $event->accepts_new_requests,
            'custom_fields' => $event->customFields->map(fn ($field) => self::customFieldToArray($field))->values()->all(),
        ];
    }

    public static function registrationToArray(Registration $registration): array
    {
        $registration->loadMissing('event', 'customAnswers.field', 'customAnswers.selectedOption');

        return [
            'id' => $registration->id,
            'event_id' => $registration->evento_id,
            'event_title' => $registration->event?->titolo,
            'status' => $registration->stato,
            'attendee_note' => $registration->nota_partecipante,
            'created_at' => self::normalize($registration->creato_il),
            'updated_at' => self::normalize($registration->aggiornato_il),
            'cancelled_at' => self::normalize($registration->annullata_il),
            'promoted_at' => self::normalize($registration->promossa_il),
            'custom_answers' => $registration->customAnswers
                ->map(fn (RegistrationCustomAnswer $answer) => [
                    'field_id' => $answer->campo_id,
                    'field_label' => $answer->field?->etichetta,
                    'field_type' => $answer->field?->tipo_campo,
                    'value' => $answer->display_value,
                    'selected_option_id' => $answer->opzione_selezionata_id,
                ])
                ->values()
                ->all(),
        ];
    }

    public static function calendarEventToArray(Event $event): array
    {
        $colors = [
            Event::TYPE_CULTURA => '#6366f1',
            Event::TYPE_SOCIALE => '#0ea5e9',
            Event::TYPE_BENEFICENZA => '#f59e0b',
            Event::TYPE_SPORT => '#22c55e',
            Event::TYPE_FORMAZIONE => '#8b5cf6',
        ];

        return [
            'id' => $event->id,
            'title' => $event->titolo,
            'start' => self::normalize($event->inizio_il),
            'end' => self::normalize($event->fine_il),
            'url' => route('events.detail', $event->slug),
            'event_type' => $event->tipo_evento,
            'color' => $colors[$event->tipo_evento] ?? '#6b7280',
        ];
    }

    public static function changelogToArray(EventChangeLog $log): array
    {
        $log->loadMissing('actor');

        return [
            'id' => $log->id,
            'actor' => $log->actor?->display_name,
            'changed_fields' => $log->campi_modificati,
            'created_at' => self::normalize($log->creato_il),
        ];
    }

    public static function feedbackToArray(EventFeedback $feedback): array
    {
        $feedback->loadMissing('user');

        return [
            'id' => $feedback->id,
            'user' => $feedback->user?->display_name,
            'rating' => $feedback->valutazione,
            'comment' => $feedback->commento,
            'created_at' => self::normalize($feedback->creato_il),
            'updated_at' => self::normalize($feedback->aggiornato_il),
        ];
    }

    public static function feedbackSummaryToArray(array $summary): array
    {
        return [
            'average_rating' => $summary['average_rating'],
            'review_count' => $summary['review_count'],
            'rating_distribution' => $summary['rating_distribution'],
        ];
    }

    public static function xmlForEvents(iterable $events, string $rootName = 'events', string $itemName = 'event', int $status = 200): Response
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><'.$rootName.'/>');
        foreach ($events as $event) {
            $node = $xml->addChild($itemName);
            foreach (self::eventToArray($event) as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $node->addChild($key, htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
        }

        return response($xml->asXML(), $status)->header('Content-Type', 'application/xml');
    }
}

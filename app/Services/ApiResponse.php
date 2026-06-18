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
            'label' => $field->label,
            'field_type' => $field->field_type,
            'is_required' => (bool) $field->is_required,
            'display_order' => $field->display_order,
            'options' => $field->field_type === EventCustomField::TYPE_SELECT
                ? $field->options->map(fn ($option) => [
                    'id' => $option->id,
                    'value' => $option->value,
                    'display_order' => $option->display_order,
                ])->values()->all()
                : [],
        ];
    }

    public static function eventToArray(Event $event): array
    {
        $event->loadMissing('customFields.options');

        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'notes' => $event->notes,
            'max_participants' => $event->max_participants,
            'price' => (string) $event->price,
            'start_datetime' => self::normalize($event->start_datetime),
            'end_datetime' => self::normalize($event->end_datetime),
            'registration_deadline' => self::normalize($event->registration_deadline),
            'event_type' => $event->event_type,
            'status' => $event->status,
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
            'event_id' => $registration->event_id,
            'event_title' => $registration->event?->title,
            'status' => $registration->status,
            'attendee_note' => $registration->attendee_note,
            'created_at' => self::normalize($registration->created_at),
            'updated_at' => self::normalize($registration->updated_at),
            'cancelled_at' => self::normalize($registration->cancelled_at),
            'promoted_at' => self::normalize($registration->promoted_at),
            'custom_answers' => $registration->customAnswers
                ->map(fn (RegistrationCustomAnswer $answer) => [
                    'field_id' => $answer->field_id,
                    'field_label' => $answer->field?->label,
                    'field_type' => $answer->field?->field_type,
                    'value' => $answer->display_value,
                    'selected_option_id' => $answer->selected_option_id,
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
            'title' => $event->title,
            'start' => self::normalize($event->start_datetime),
            'end' => self::normalize($event->end_datetime),
            'url' => route('events.detail', $event->slug),
            'event_type' => $event->event_type,
            'color' => $colors[$event->event_type] ?? '#6b7280',
        ];
    }

    public static function changelogToArray(EventChangeLog $log): array
    {
        $log->loadMissing('actor');

        return [
            'id' => $log->id,
            'actor' => $log->actor?->display_name,
            'changed_fields' => $log->changed_fields,
            'created_at' => self::normalize($log->created_at),
        ];
    }

    public static function feedbackToArray(EventFeedback $feedback): array
    {
        $feedback->loadMissing('user');

        return [
            'id' => $feedback->id,
            'user' => $feedback->user?->display_name,
            'rating' => $feedback->rating,
            'comment' => $feedback->comment,
            'created_at' => self::normalize($feedback->created_at),
            'updated_at' => self::normalize($feedback->updated_at),
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

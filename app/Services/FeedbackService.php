<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventFeedback;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class FeedbackService
{
    public function __construct(private readonly EventService $events)
    {
    }

    public function validateEligibility(Event $event, User $user): Registration
    {
        $this->events->syncEventStatus($event);
        $event->refresh();

        if ($event->stato !== Event::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['event' => 'Il feedback e disponibile solo per eventi completati.']);
        }

        $registration = Registration::query()
            ->where('evento_id', $event->id)
            ->where('utente_id', $user->id)
            ->where('stato', Registration::STATUS_ACTIVE)
            ->first();

        if (! $registration) {
            throw new AuthorizationException('Solo i partecipanti confermati possono lasciare un feedback.');
        }

        return $registration;
    }

    public function createOrUpdate(Event $event, User $user, int $rating, string $comment = ''): EventFeedback
    {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['rating' => 'Il voto deve essere compreso tra 1 e 5.']);
        }

        $registration = $this->validateEligibility($event, $user);

        return EventFeedback::query()->updateOrCreate(
            [
                'evento_id' => $event->id,
                'utente_id' => $user->id,
            ],
            [
                'iscrizione_id' => $registration->id,
                'valutazione' => $rating,
                'commento' => trim($comment),
            ],
        );
    }

    public function summary(Event $event): array
    {
        $feedbacks = $event->feedbacks();
        $reviewCount = (int) $feedbacks->count();
        $average = $reviewCount > 0 ? round((float) $feedbacks->avg('valutazione'), 1) : null;
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($event->feedbacks()->selectRaw('valutazione, count(*) as aggregate_count')->groupBy('valutazione')->get() as $row) {
            $distribution[(int) $row->valutazione] = (int) $row->aggregate_count;
        }

        return [
            'average_rating' => $average,
            'review_count' => $reviewCount,
            'rating_distribution' => $distribution,
        ];
    }
}

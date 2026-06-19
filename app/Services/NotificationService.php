<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Notification;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;

class NotificationService
{
    public function create(User $recipient, string $type, string $text, ?Event $event = null, ?Registration $registration = null): Notification
    {
        if (! $event && $registration) {
            $event = $registration->event;
        }

        return Notification::query()->create([
            'destinatario_id' => $recipient->id,
            'tipo_notifica' => $type,
            'testo' => $text,
            'evento_id' => $event?->id,
            'iscrizione_id' => $registration?->id,
            'letta' => false,
        ]);
    }

    public function markAllAsRead(User $user): int
    {
        $notifications = Notification::query()->where('destinatario_id', $user->id)->where('letta', false)->get();
        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        return $notifications->count();
    }

    public function targetUrl(Notification $notification, User $user): string
    {
        $notification->loadMissing('event', 'registration');

        if ($user->hasRole('admin') && $notification->evento_id) {
            return route('events.manage-update', $notification->evento_id);
        }

        if ($notification->iscrizione_id && $notification->registration?->utente_id === $user->id) {
            return route('events.my-registrations');
        }

        if (
            $notification->evento_id
            && $notification->event
            && in_array($notification->event->stato, [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED], true)
        ) {
            return route('events.detail', $notification->event->slug);
        }

        return route('events.my-registrations');
    }

    public function serialize(Notification $notification, User $user): array
    {
        return [
            'id' => $notification->id,
            'text' => $notification->testo,
            'notification_type' => $notification->tipo_notifica,
            'is_read' => (bool) $notification->letta,
            'created_at' => $notification->creato_il?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'target_url' => $this->targetUrl($notification, $user),
        ];
    }

    public function sendDeadlineReminders(): int
    {
        $now = Carbon::now();
        $tomorrow = Carbon::now()->addDay();
        $todayStart = Carbon::today();
        $todayEnd = $todayStart->copy()->endOfDay();
        $count = 0;

        Event::query()
            ->where('stato', Event::STATUS_PUBLISHED)
            ->whereBetween('scadenza_iscrizioni', [$now, $tomorrow])
            ->with(['registrations' => fn ($query) => $query->where('stato', Registration::STATUS_ACTIVE)->with('user')])
            ->chunkById(100, function ($events) use (&$count, $todayStart, $todayEnd) {
                foreach ($events as $event) {
                    foreach ($event->registrations as $registration) {
                        $alreadySentToday = Notification::query()
                            ->where('destinatario_id', $registration->utente_id)
                            ->where('tipo_notifica', Notification::TYPE_REGISTRATION_DEADLINE_REMINDER)
                            ->where('evento_id', $event->id)
                            ->where('iscrizione_id', $registration->id)
                            ->whereBetween('creato_il', [$todayStart, $todayEnd])
                            ->exists();

                        if ($alreadySentToday) {
                            continue;
                        }

                        $this->create(
                            $registration->user,
                            Notification::TYPE_REGISTRATION_DEADLINE_REMINDER,
                            "Il termine iscrizioni per l'evento '{$event->titolo}' e vicino.",
                            $event,
                            $registration,
                        );
                        $count++;
                    }
                }
            });

        return $count;
    }
}

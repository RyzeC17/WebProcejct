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
            'recipient_id' => $recipient->id,
            'notification_type' => $type,
            'text' => $text,
            'event_id' => $event?->id,
            'registration_id' => $registration?->id,
            'is_read' => false,
        ]);
    }

    public function markAllAsRead(User $user): int
    {
        $notifications = Notification::query()->where('recipient_id', $user->id)->where('is_read', false)->get();
        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        return $notifications->count();
    }

    public function targetUrl(Notification $notification, User $user): string
    {
        $notification->loadMissing('event', 'registration');

        if ($user->is_staff && $notification->event_id) {
            return route('events.manage-update', $notification->event_id);
        }

        if ($notification->registration_id && $notification->registration?->user_id === $user->id) {
            return route('events.my-registrations');
        }

        if (
            $notification->event_id
            && $notification->event
            && in_array($notification->event->status, [Event::STATUS_PUBLISHED, Event::STATUS_COMPLETED], true)
        ) {
            return route('events.detail', $notification->event->slug);
        }

        return route('events.my-registrations');
    }

    public function serialize(Notification $notification, User $user): array
    {
        return [
            'id' => $notification->id,
            'text' => $notification->text,
            'notification_type' => $notification->notification_type,
            'is_read' => (bool) $notification->is_read,
            'created_at' => $notification->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
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
            ->where('status', Event::STATUS_PUBLISHED)
            ->whereBetween('registration_deadline', [$now, $tomorrow])
            ->with(['registrations' => fn ($query) => $query->where('status', Registration::STATUS_ACTIVE)->with('user')])
            ->chunkById(100, function ($events) use (&$count, $todayStart, $todayEnd) {
                foreach ($events as $event) {
                    foreach ($event->registrations as $registration) {
                        $alreadySentToday = Notification::query()
                            ->where('recipient_id', $registration->user_id)
                            ->where('notification_type', Notification::TYPE_REGISTRATION_DEADLINE_REMINDER)
                            ->where('event_id', $event->id)
                            ->where('registration_id', $registration->id)
                            ->whereBetween('created_at', [$todayStart, $todayEnd])
                            ->exists();

                        if ($alreadySentToday) {
                            continue;
                        }

                        $this->create(
                            $registration->user,
                            Notification::TYPE_REGISTRATION_DEADLINE_REMINDER,
                            "Il termine iscrizioni per l'evento '{$event->title}' e vicino.",
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

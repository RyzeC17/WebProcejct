<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Notification extends Model
{
    public const TYPE_REGISTRATION_CONFIRMED = 'registration_confirmed';
    public const TYPE_EVENT_UPDATED = 'event_updated';
    public const TYPE_EVENT_CANCELLED = 'event_cancelled';
    public const TYPE_EVENT_FULL = 'event_full';
    public const TYPE_WAITLIST_PROMOTED = 'waitlist_promoted';
    public const TYPE_REGISTRATION_DEADLINE_REMINDER = 'registration_deadline_reminder';

    public const TYPES = [
        self::TYPE_REGISTRATION_CONFIRMED => 'Iscrizione confermata',
        self::TYPE_EVENT_UPDATED => 'Evento modificato',
        self::TYPE_EVENT_CANCELLED => 'Evento annullato',
        self::TYPE_EVENT_FULL => 'Posti esauriti',
        self::TYPE_WAITLIST_PROMOTED => "Promozione lista d'attesa",
        self::TYPE_REGISTRATION_DEADLINE_REMINDER => 'Scadenza iscrizioni',
    ];

    protected $table = 'notifiche';

    public const UPDATED_AT = null;

    protected $fillable = [
        'recipient_id',
        'notification_type',
        'text',
        'event_id',
        'registration_id',
        'is_read',
        'created_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'created_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function getNotificationTypeLabelAttribute(): string
    {
        return self::TYPES[$this->notification_type] ?? $this->notification_type;
    }

    public function markAsRead(): void
    {
        if ($this->is_read) {
            return;
        }

        $this->forceFill([
            'is_read' => true,
            'read_at' => Carbon::now(),
        ])->save();
    }
}

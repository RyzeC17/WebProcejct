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

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = null;

    protected $fillable = [
        'destinatario_id',
        'tipo_notifica',
        'testo',
        'evento_id',
        'iscrizione_id',
        'letta',
        'creato_il',
        'letta_il',
    ];

    protected function casts(): array
    {
        return [
            'letta' => 'boolean',
            'creato_il' => 'datetime',
            'letta_il' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'evento_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'iscrizione_id');
    }

    public function getNotificationTypeLabelAttribute(): string
    {
        return self::TYPES[$this->tipo_notifica] ?? $this->tipo_notifica;
    }

    public function markAsRead(): void
    {
        if ($this->letta) {
            return;
        }

        $this->forceFill([
            'letta' => true,
            'letta_il' => Carbon::now(),
        ])->save();
    }
}

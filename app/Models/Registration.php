<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_WAITLISTED = 'waitlisted';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Attiva',
        self::STATUS_WAITLISTED => "Lista d'attesa",
        self::STATUS_CANCELLED => 'Annullata',
    ];

    protected $table = 'iscrizioni';

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'attendee_note',
        'cancelled_at',
        'promoted_at',
    ];

    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
            'promoted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customAnswers(): HasMany
    {
        return $this->hasMany(RegistrationCustomAnswer::class, 'registration_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'registration_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class, 'registration_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getIsConfirmedAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}

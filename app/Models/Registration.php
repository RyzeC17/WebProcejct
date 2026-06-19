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

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = 'aggiornato_il';

    protected $fillable = [
        'evento_id',
        'utente_id',
        'stato',
        'nota_partecipante',
        'annullata_il',
        'promossa_il',
    ];

    protected function casts(): array
    {
        return [
            'annullata_il' => 'datetime',
            'promossa_il' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'evento_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utente_id');
    }

    public function customAnswers(): HasMany
    {
        return $this->hasMany(RegistrationCustomAnswer::class, 'iscrizione_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'iscrizione_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class, 'iscrizione_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->stato] ?? $this->stato;
    }

    public function getIsConfirmedAttribute(): bool
    {
        return $this->stato === self::STATUS_ACTIVE;
    }
}

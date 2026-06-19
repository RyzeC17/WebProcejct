<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Event extends Model
{
    public const TYPE_CULTURA = 'cultura';
    public const TYPE_SOCIALE = 'sociale';
    public const TYPE_BENEFICENZA = 'beneficenza';
    public const TYPE_SPORT = 'sport';
    public const TYPE_FORMAZIONE = 'formazione';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const EVENT_TYPES = [
        self::TYPE_CULTURA => 'Culturale',
        self::TYPE_SOCIALE => 'Sociale',
        self::TYPE_BENEFICENZA => 'Beneficenza',
        self::TYPE_SPORT => 'Sportivo',
        self::TYPE_FORMAZIONE => 'Formativo',
    ];

    public const STATUSES = [
        self::STATUS_DRAFT => 'Bozza',
        self::STATUS_PUBLISHED => 'Pubblicato',
        self::STATUS_CLOSED => 'Chiuso',
        self::STATUS_COMPLETED => 'Completato',
        self::STATUS_CANCELLED => 'Annullato',
    ];

    public const OPERATIONAL_STATE_LABELS = [
        'cancelled' => 'Annullato',
        'completed' => 'Completato',
        'ongoing' => 'In corso',
        'full' => 'Esaurito',
        'registration_expired' => 'Iscrizioni chiuse',
        'available' => 'Disponibile',
    ];

    protected $table = 'eventi';

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = 'aggiornato_il';

    protected $fillable = [
        'titolo',
        'slug',
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
        'creato_da_id',
    ];

    protected function casts(): array
    {
        return [
            'prezzo' => 'decimal:2',
            'max_partecipanti' => 'integer',
            'inizio_il' => 'datetime',
            'fine_il' => 'datetime',
            'scadenza_iscrizioni' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event): void {
            if (! $event->slug) {
                $event->slug = static::buildUniqueSlug($event);
            }
        });
    }

    public static function buildUniqueSlug(Event $event): string
    {
        $base = Str::slug(Str::limit($event->titolo ?: 'evento', 45, '')) ?: 'evento';
        $slug = $base;
        $index = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($event->exists, fn (Builder $query) => $query->whereKeyNot($event->getKey()))
            ->exists()) {
            $slug = Str::limit($base, 40, '').'-'.$index;
            $index++;
        }

        return $slug;
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->whereIn('stato', [self::STATUS_PUBLISHED, self::STATUS_COMPLETED]);
    }

    public function scopeWithRegistrationCounts(Builder $query): Builder
    {
        return $query->withCount([
            'registrations as active_registrations_count' => fn (Builder $query) => $query->where('stato', Registration::STATUS_ACTIVE),
            'registrations as waitlisted_registrations_count' => fn (Builder $query) => $query->where('stato', Registration::STATUS_WAITLISTED),
        ]);
    }

    public function loadRegistrationCounts(): self
    {
        $this->loadCount([
            'registrations as active_registrations_count' => fn (Builder $query) => $query->where('stato', Registration::STATUS_ACTIVE),
            'registrations as waitlisted_registrations_count' => fn (Builder $query) => $query->where('stato', Registration::STATUS_WAITLISTED),
        ]);

        return $this;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'evento_id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(EventCustomField::class, 'evento_id')->orderBy('ordine_visualizzazione')->orderBy('id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(EventChangeLog::class, 'evento_id')->latest('creato_il')->latest('id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class, 'evento_id')->latest('creato_il');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'evento_id');
    }

    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->tipo_evento] ?? $this->tipo_evento;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->stato] ?? $this->stato;
    }

    public function getActiveRegistrationsCountAttribute(): int
    {
        if (array_key_exists('active_registrations_count', $this->attributes)) {
            return (int) $this->attributes['active_registrations_count'];
        }

        return (int) $this->registrations()->where('stato', Registration::STATUS_ACTIVE)->count();
    }

    public function getWaitlistedRegistrationsCountAttribute(): int
    {
        if (array_key_exists('waitlisted_registrations_count', $this->attributes)) {
            return (int) $this->attributes['waitlisted_registrations_count'];
        }

        return (int) $this->registrations()->where('stato', Registration::STATUS_WAITLISTED)->count();
    }

    public function getRemainingSeatsAttribute(): int
    {
        return max((int) $this->max_partecipanti - $this->active_registrations_count, 0);
    }

    public function getAcceptsNewRequestsAttribute(): bool
    {
        $now = Carbon::now();

        return $this->stato === self::STATUS_PUBLISHED
            && $this->scadenza_iscrizioni?->greaterThanOrEqualTo($now)
            && $this->inizio_il?->greaterThan($now);
    }

    public function getIsRegistrationOpenAttribute(): bool
    {
        return $this->accepts_new_requests && $this->remaining_seats > 0;
    }

    public function getCanConfigureCustomFieldsAttribute(): bool
    {
        if ($this->stato === self::STATUS_DRAFT) {
            return true;
        }

        if ($this->stato === self::STATUS_PUBLISHED) {
            return ! $this->registrations()
                ->whereIn('stato', [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED])
                ->exists();
        }

        return false;
    }

    public function getOperationalStateAttribute(): string
    {
        $now = Carbon::now();

        if ($this->stato === self::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($this->stato === self::STATUS_COMPLETED || $this->fine_il?->lessThan($now)) {
            return 'completed';
        }

        if ($this->inizio_il?->lessThanOrEqualTo($now) && $this->fine_il?->greaterThanOrEqualTo($now)) {
            return 'ongoing';
        }

        if ($this->remaining_seats <= 0) {
            return 'full';
        }

        if ($this->scadenza_iscrizioni?->lessThan($now) || $this->stato === self::STATUS_CLOSED) {
            return 'registration_expired';
        }

        return 'available';
    }

    public function getOperationalStateLabelAttribute(): string
    {
        return self::OPERATIONAL_STATE_LABELS[$this->operational_state] ?? $this->operational_state;
    }
}

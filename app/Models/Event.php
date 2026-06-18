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

    protected $fillable = [
        'title',
        'slug',
        'description',
        'venue_name',
        'venue_address',
        'notes',
        'max_participants',
        'price',
        'start_datetime',
        'end_datetime',
        'registration_deadline',
        'event_type',
        'status',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_participants' => 'integer',
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'registration_deadline' => 'datetime',
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
        $base = Str::slug(Str::limit($event->title ?: 'evento', 45, '')) ?: 'evento';
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
        return $query->whereIn('status', [self::STATUS_PUBLISHED, self::STATUS_COMPLETED]);
    }

    public function scopeWithRegistrationCounts(Builder $query): Builder
    {
        return $query->withCount([
            'registrations as active_registrations_count' => fn (Builder $query) => $query->where('status', Registration::STATUS_ACTIVE),
            'registrations as waitlisted_registrations_count' => fn (Builder $query) => $query->where('status', Registration::STATUS_WAITLISTED),
        ]);
    }

    public function loadRegistrationCounts(): self
    {
        $this->loadCount([
            'registrations as active_registrations_count' => fn (Builder $query) => $query->where('status', Registration::STATUS_ACTIVE),
            'registrations as waitlisted_registrations_count' => fn (Builder $query) => $query->where('status', Registration::STATUS_WAITLISTED),
        ]);

        return $this;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'event_id');
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(EventCustomField::class, 'event_id')->orderBy('display_order')->orderBy('id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(EventChangeLog::class, 'event_id')->latest('created_at')->latest('id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(EventFeedback::class, 'event_id')->latest('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'event_id');
    }

    public function getEventTypeLabelAttribute(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getActiveRegistrationsCountAttribute(): int
    {
        if (array_key_exists('active_registrations_count', $this->attributes)) {
            return (int) $this->attributes['active_registrations_count'];
        }

        return (int) $this->registrations()->where('status', Registration::STATUS_ACTIVE)->count();
    }

    public function getWaitlistedRegistrationsCountAttribute(): int
    {
        if (array_key_exists('waitlisted_registrations_count', $this->attributes)) {
            return (int) $this->attributes['waitlisted_registrations_count'];
        }

        return (int) $this->registrations()->where('status', Registration::STATUS_WAITLISTED)->count();
    }

    public function getRemainingSeatsAttribute(): int
    {
        return max((int) $this->max_participants - $this->active_registrations_count, 0);
    }

    public function getAcceptsNewRequestsAttribute(): bool
    {
        $now = Carbon::now();

        return $this->status === self::STATUS_PUBLISHED
            && $this->registration_deadline?->greaterThanOrEqualTo($now)
            && $this->start_datetime?->greaterThan($now);
    }

    public function getIsRegistrationOpenAttribute(): bool
    {
        return $this->accepts_new_requests && $this->remaining_seats > 0;
    }

    public function getCanConfigureCustomFieldsAttribute(): bool
    {
        if ($this->status === self::STATUS_DRAFT) {
            return true;
        }

        if ($this->status === self::STATUS_PUBLISHED) {
            return ! $this->registrations()
                ->whereIn('status', [Registration::STATUS_ACTIVE, Registration::STATUS_WAITLISTED])
                ->exists();
        }

        return false;
    }

    public function getOperationalStateAttribute(): string
    {
        $now = Carbon::now();

        if ($this->status === self::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($this->status === self::STATUS_COMPLETED || $this->end_datetime?->lessThan($now)) {
            return 'completed';
        }

        if ($this->start_datetime?->lessThanOrEqualTo($now) && $this->end_datetime?->greaterThanOrEqualTo($now)) {
            return 'ongoing';
        }

        if ($this->remaining_seats <= 0) {
            return 'full';
        }

        if ($this->registration_deadline?->lessThan($now) || $this->status === self::STATUS_CLOSED) {
            return 'registration_expired';
        }

        return 'available';
    }

    public function getOperationalStateLabelAttribute(): string
    {
        return self::OPERATIONAL_STATE_LABELS[$this->operational_state] ?? $this->operational_state;
    }
}

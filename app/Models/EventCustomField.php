<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCustomField extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';

    public const FIELD_TYPES = [
        self::TYPE_TEXT => 'Testo',
        self::TYPE_NUMBER => 'Numero',
        self::TYPE_BOOLEAN => 'Booleano',
        self::TYPE_SELECT => 'Selezione singola',
    ];

    protected $table = 'campi_evento';

    protected $fillable = [
        'event_id',
        'label',
        'field_type',
        'is_required',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class, 'field_id')->orderBy('display_order')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationCustomAnswer::class, 'field_id');
    }

    public function getFormFieldNameAttribute(): string
    {
        return 'custom_field_'.$this->getKey();
    }

    public function getFieldTypeLabelAttribute(): string
    {
        return self::FIELD_TYPES[$this->field_type] ?? $this->field_type;
    }
}

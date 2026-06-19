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

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = 'aggiornato_il';

    protected $fillable = [
        'evento_id',
        'etichetta',
        'tipo_campo',
        'obbligatorio',
        'ordine_visualizzazione',
    ];

    protected function casts(): array
    {
        return [
            'obbligatorio' => 'boolean',
            'ordine_visualizzazione' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'evento_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class, 'campo_id')->orderBy('ordine_visualizzazione')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationCustomAnswer::class, 'campo_id');
    }

    public function getFormFieldNameAttribute(): string
    {
        return 'custom_field_'.$this->getKey();
    }

    public function getFieldTypeLabelAttribute(): string
    {
        return self::FIELD_TYPES[$this->tipo_campo] ?? $this->tipo_campo;
    }
}

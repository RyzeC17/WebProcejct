<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationCustomAnswer extends Model
{
    protected $table = 'risposte_iscrizione';

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = 'aggiornato_il';

    protected $fillable = [
        'iscrizione_id',
        'campo_id',
        'valore_testo',
        'valore_numero',
        'valore_booleano',
        'opzione_selezionata_id',
    ];

    protected function casts(): array
    {
        return [
            'valore_numero' => 'decimal:4',
            'valore_booleano' => 'boolean',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'iscrizione_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class, 'campo_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(FieldOption::class, 'opzione_selezionata_id');
    }

    public function getDisplayValueAttribute(): string
    {
        $fieldType = $this->field?->tipo_campo;

        return match ($fieldType) {
            EventCustomField::TYPE_TEXT => (string) $this->valore_testo,
            EventCustomField::TYPE_NUMBER => (string) $this->valore_numero,
            EventCustomField::TYPE_BOOLEAN => $this->valore_booleano ? 'Si' : 'No',
            EventCustomField::TYPE_SELECT => (string) ($this->selectedOption?->valore ?? ''),
            default => '',
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationCustomAnswer extends Model
{
    protected $table = 'risposte_iscrizione';

    protected $fillable = [
        'registration_id',
        'field_id',
        'text_value',
        'number_value',
        'boolean_value',
        'selected_option_id',
    ];

    protected function casts(): array
    {
        return [
            'number_value' => 'decimal:4',
            'boolean_value' => 'boolean',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'registration_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class, 'field_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(FieldOption::class, 'selected_option_id');
    }

    public function getDisplayValueAttribute(): string
    {
        $fieldType = $this->field?->field_type;

        return match ($fieldType) {
            EventCustomField::TYPE_TEXT => (string) $this->text_value,
            EventCustomField::TYPE_NUMBER => (string) $this->number_value,
            EventCustomField::TYPE_BOOLEAN => $this->boolean_value ? 'Si' : 'No',
            EventCustomField::TYPE_SELECT => (string) ($this->selectedOption?->value ?? ''),
            default => '',
        };
    }
}

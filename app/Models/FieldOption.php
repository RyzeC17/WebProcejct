<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldOption extends Model
{
    protected $table = 'opzioni_campo';

    public $timestamps = false;

    protected $fillable = [
        'field_id',
        'value',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class, 'field_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationCustomAnswer::class, 'selected_option_id');
    }
}

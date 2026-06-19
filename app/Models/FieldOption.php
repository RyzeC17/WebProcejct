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
        'campo_id',
        'valore',
        'ordine_visualizzazione',
    ];

    protected function casts(): array
    {
        return [
            'ordine_visualizzazione' => 'integer',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(EventCustomField::class, 'campo_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RegistrationCustomAnswer::class, 'opzione_selezionata_id');
    }
}

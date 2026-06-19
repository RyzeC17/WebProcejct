<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventChangeLog extends Model
{
    protected $table = 'modifiche_evento';

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = null;

    protected $fillable = [
        'evento_id',
        'autore_id',
        'campi_modificati',
        'creato_il',
    ];

    protected function casts(): array
    {
        return [
            'campi_modificati' => 'array',
            'creato_il' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'evento_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autore_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeedback extends Model
{
    protected $table = 'feedback_evento';

    public const CREATED_AT = 'creato_il';
    public const UPDATED_AT = 'aggiornato_il';

    protected $fillable = [
        'evento_id',
        'utente_id',
        'iscrizione_id',
        'valutazione',
        'commento',
    ];

    protected function casts(): array
    {
        return [
            'valutazione' => 'integer',
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

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class, 'iscrizione_id');
    }
}

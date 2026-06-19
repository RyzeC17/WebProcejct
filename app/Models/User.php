<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $table = 'utenti';

    public $timestamps = false;

    protected string $guard_name = 'web';

    protected $fillable = [
        'nome_utente',
        'name',
        'email',
        'nome',
        'cognome',
        'password',
        'attivo',
        'ultimo_accesso',
        'data_iscrizione',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
            'ultimo_accesso' => 'datetime',
            'data_iscrizione' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->nome ?? '').' '.($this->cognome ?? ''));
    }

    public function getNameAttribute(): string
    {
        return $this->display_name;
    }

    public function setNameAttribute(?string $value): void
    {
        $parts = preg_split('/\s+/', trim((string) $value), 2);

        $this->attributes['nome'] = $parts[0] ?? '';
        $this->attributes['cognome'] = $parts[1] ?? '';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->nome_utente;
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'creato_da_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'utente_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'destinatario_id');
    }
}

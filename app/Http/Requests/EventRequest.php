<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'venue_name' => ['required', 'string', 'max:255'],
            'venue_address' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'max_participants' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'registration_deadline' => ['required', 'date', 'before_or_equal:start_datetime'],
            'event_type' => ['required', Rule::in(array_keys(Event::EVENT_TYPES))],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
        ];
    }

    public function eventAttributes(): array
    {
        $data = $this->safe()->only([
            'title',
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
        ]);

        return [
            'titolo' => $data['title'],
            'descrizione' => $data['description'],
            'nome_luogo' => $data['venue_name'],
            'indirizzo_luogo' => $data['venue_address'],
            'note' => $data['notes'] ?? '',
            'max_partecipanti' => $data['max_participants'],
            'prezzo' => $data['price'],
            'inizio_il' => $data['start_datetime'],
            'fine_il' => $data['end_datetime'],
            'scadenza_iscrizioni' => $data['registration_deadline'],
            'tipo_evento' => $data['event_type'],
            'stato' => $data['status'],
        ];
    }
}

<form data-json-form action="{{ route('events.update-registration', $registration->id) }}" method="post" class="mb-3">
    @csrf
    <div class="form-group sidebar-form-group">
        <label class="form-label" for="registration-note-{{ $registration->id }}">Aggiorna la tua nota</label>
        <textarea class="form-control" id="registration-note-{{ $registration->id }}" name="attendee_note" rows="3">{{ $registration->nota_partecipante }}</textarea>
    </div>
    <button class="btn btn-outline-dark w-100 mt-3" type="submit" data-loading-label="Salvataggio...">Salva nota</button>
</form>
<form data-json-form action="{{ route('events.cancel-registration', $registration->id) }}" method="post">
    @csrf
    <button class="btn btn-outline-danger w-100" type="submit" data-loading-label="Annullamento...">{{ $cancelLabel }}</button>
</form>

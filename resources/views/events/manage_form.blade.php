@extends('layouts.app')

@php
    $isEdit = $event->exists;
    $rows = old('custom_fields');
    if ($rows === null) {
        $rows = $customFields->map(fn ($field) => [
            'id' => $field->id,
            'label' => $field->label,
            'field_type' => $field->field_type,
            'display_order' => $field->display_order,
            'is_required' => $field->is_required,
            'options_text' => $field->options->pluck('value')->implode("\n"),
        ])->values()->all();
    }
    if ($canEditCustomFields && count($rows) === 0) {
        $rows[] = ['label' => '', 'field_type' => 'text', 'display_order' => 1, 'is_required' => false, 'options_text' => ''];
    }
@endphp

@section('title', ($isEdit ? 'Modifica Evento' : 'Nuovo Evento').' | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 section-heading">
                    <div>
                        <span class="eyebrow">Backoffice amministratore</span>
                        <h1 class="display-6 fw-bold mb-1">{{ $isEdit ? 'Modifica evento' : 'Crea un nuovo evento' }}</h1>
                        <p class="text-muted mb-0">Compila tutte le informazioni richieste e controlla i vincoli temporali.</p>
                    </div>
                    <a class="btn btn-outline-dark" href="{{ route('events.manage-list') }}">Torna alla lista</a>
                </div>

                <div class="card border-0 shadow-soft form-shell">
                    <div class="card-body p-4 p-lg-5">
                        <form method="post" novalidate>
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6 field-group form-group">
                                    <label class="form-label" for="title">Titolo</label>
                                    <input class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="255" value="{{ old('title', $event->title) }}" required>
                                    @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 field-group form-group">
                                    <label class="form-label" for="venue_name">Luogo</label>
                                    <input class="form-control @error('venue_name') is-invalid @enderror" id="venue_name" name="venue_name" value="{{ old('venue_name', $event->venue_name) }}" required>
                                    @error('venue_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 field-group form-group">
                                    <label class="form-label" for="description">Descrizione</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" required>{{ old('description', $event->description) }}</textarea>
                                    @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 field-group form-group">
                                    <label class="form-label" for="venue_address">Indirizzo</label>
                                    <textarea class="form-control @error('venue_address') is-invalid @enderror" id="venue_address" name="venue_address" rows="2" required>{{ old('venue_address', $event->venue_address) }}</textarea>
                                    @error('venue_address')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12 field-group form-group">
                                    <label class="form-label" for="notes">Note</label>
                                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes', $event->notes) }}</textarea>
                                    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 field-group form-group">
                                    <label class="form-label" for="max_participants">Numero massimo di partecipanti</label>
                                    <input class="form-control @error('max_participants') is-invalid @enderror" id="max_participants" name="max_participants" type="number" min="1" value="{{ old('max_participants', $event->max_participants) }}" required>
                                    @error('max_participants')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 field-group form-group">
                                    <label class="form-label" for="price">Costo</label>
                                    <input class="form-control @error('price') is-invalid @enderror" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $event->price ?? 0) }}" required>
                                    @error('price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                @foreach (['start_datetime' => 'Data e ora di inizio', 'end_datetime' => 'Data e ora di fine', 'registration_deadline' => 'Termine iscrizioni'] as $name => $label)
                                    <div class="col-md-6 field-group form-group">
                                        <label class="form-label" for="{{ $name }}">{{ $label }}</label>
                                        @php $dateValue = old($name, $event->{$name} ? $event->{$name}->format('Y-m-d\TH:i') : ''); @endphp
                                        <input class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" type="datetime-local" value="{{ $dateValue }}" required>
                                        @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                                <div class="col-md-6 field-group form-group">
                                    <label class="form-label" for="event_type">Tipologia</label>
                                    <select class="form-select @error('event_type') is-invalid @enderror" id="event_type" name="event_type" required>
                                        @foreach ($eventTypes as $value => $label)
                                            <option value="{{ $value }}" @selected(old('event_type', $event->event_type) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('event_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 field-group form-group">
                                    <label class="form-label" for="status">Stato</label>
                                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                        @foreach ($statusChoices as $value => $label)
                                            <option value="{{ $value }}" @selected(old('status', $event->status ?: 'draft') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Gli eventi pubblicati sono visibili pubblicamente.</div>
                                    @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <hr class="my-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
                                <div>
                                    <h2 class="h4 mb-1">Campi aggiuntivi iscrizione</h2>
                                    <p class="text-muted mb-0">Configura i dati extra richiesti agli utenti al momento dell'adesione.</p>
                                </div>
                                @if ($canEditCustomFields)
                                    <button class="btn btn-outline-dark" type="button" data-add-custom-field>Aggiungi campo</button>
                                @endif
                            </div>

                            @if ($canEditCustomFields)
                                <input type="hidden" id="id_custom_fields-TOTAL_FORMS" name="custom_fields_total" value="{{ count($rows) }}">
                                <div class="custom-field-formset" data-custom-field-formset data-prefix="custom_fields">
                                    <div class="d-flex flex-column gap-3" data-custom-field-list>
                                        @foreach ($rows as $index => $row)
                                            @include('events.partials.custom_field_row', ['index' => $index, 'row' => $row, 'fieldTypes' => $fieldTypes])
                                        @endforeach
                                    </div>
                                    <template data-empty-custom-field-template>
                                        @include('events.partials.custom_field_row', ['index' => '__prefix__', 'row' => ['label' => '', 'field_type' => 'text', 'display_order' => '', 'is_required' => false, 'options_text' => ''], 'fieldTypes' => $fieldTypes])
                                    </template>
                                </div>
                            @else
                                <div class="alert alert-warning app-alert">
                                    I campi aggiuntivi possono essere modificati solo in stato bozza o prima della prima iscrizione attiva/lista d'attesa.
                                </div>
                                <div class="custom-field-summary-grid">
                                    @forelse ($customFields as $customField)
                                        <article class="custom-field-summary-card">
                                            <h3 class="h6 mb-1">{{ $customField->label }}</h3>
                                            <div class="small text-muted">{{ $customField->field_type_label }} &middot; Ordine {{ $customField->display_order }} &middot; {{ $customField->is_required ? 'Obbligatorio' : 'Facoltativo' }}</div>
                                            @if ($customField->field_type === 'select')
                                                <ul class="mb-0 mt-2 small text-muted">
                                                    @foreach ($customField->options as $option)<li>{{ $option->value }}</li>@endforeach
                                                </ul>
                                            @endif
                                        </article>
                                    @empty
                                        <div class="empty-state py-4">Nessun campo aggiuntivo configurato.</div>
                                    @endforelse
                                </div>
                            @endif
                            <button class="btn btn-dark mt-4" type="submit">{{ $isEdit ? 'Salva modifiche' : 'Crea evento' }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

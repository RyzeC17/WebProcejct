@extends('layouts.app')

@section('title', 'Eventi | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4 section-heading">
            <div>
                <span class="eyebrow">Catalogo pubblico</span>
                <h1 class="display-6 fw-bold mb-2">Eventi disponibili</h1>
                <p class="text-muted mb-0">Filtra in tempo reale gli eventi pubblicati senza ricaricare la pagina.</p>
            </div>
            @if(auth()->user()?->is_staff)
                <a class="btn btn-dark" href="{{ route('events.manage-create') }}">Nuovo evento</a>
            @endif
        </div>

        <form class="card border-0 shadow-soft p-3 p-lg-4 mb-4 filter-panel" data-filter-form data-target="#event-list-container" role="search">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Filtri di ricerca</h2>
                    <p class="small text-muted mb-0">Aggiornamento dinamico dei risultati durante la digitazione.</p>
                </div>
                <span class="filter-hint">Ricerca live attiva</span>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="filter-q">Ricerca</label>
                    <input class="form-control" id="filter-q" type="text" name="q" value="{{ $filters['search'] }}" placeholder="Titolo, descrizione, luogo">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="filter-type">Tipologia</label>
                    <select class="form-select" id="filter-type" name="event_type">
                        <option value="">Tutte</option>
                        @foreach ($eventTypes as $value => $label)
                            <option value="{{ $value }}" @selected($filters['eventType'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="filter-date">Data</label>
                    <input class="form-control" id="filter-date" type="date" name="date" value="{{ $filters['eventDate'] }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="filter-availability">Disponibilita</label>
                    <select class="form-select" id="filter-availability" name="availability">
                        <option value="">Tutte</option>
                        <option value="available" @selected($filters['availability'] === 'available')>Disponibili</option>
                        <option value="full" @selected($filters['availability'] === 'full')>Completi</option>
                        <option value="registration_expired" @selected($filters['availability'] === 'registration_expired')>Scaduti</option>
                        <option value="ongoing" @selected($filters['availability'] === 'ongoing')>In corso</option>
                    </select>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3 filter-results-meta">
            <p class="small text-muted mb-0">Card, spaziature e stato degli eventi sono ottimizzati per desktop e mobile.</p>
            <span class="small text-muted">Risultati aggiornati in tempo reale</span>
        </div>

        <div id="event-list-container" class="result-grid" aria-live="polite">
            @include('events.partials.event_cards')
        </div>
    </div>
</section>
@endsection

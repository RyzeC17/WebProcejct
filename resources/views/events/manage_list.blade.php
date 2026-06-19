@extends('layouts.app')

@section('title', 'Gestione Eventi | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end mb-4 section-heading">
            <div>
                <span class="eyebrow">Backoffice amministratore</span>
                <h1 class="display-6 fw-bold mb-1">Gestione eventi</h1>
                <p class="text-muted mb-0">Crea, aggiorna, pubblica, chiudi o annulla gli eventi dell'applicazione.</p>
            </div>
            <a class="btn btn-dark" href="{{ route('events.manage-create') }}">Nuovo evento</a>
        </div>

        <div class="ajax-feedback mb-3" id="manage-status-feedback" aria-live="polite"></div>

        <form class="card border-0 shadow-soft p-3 p-lg-4 mb-4 filter-panel" method="get">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="manage-q">Ricerca</label>
                    <input class="form-control" type="text" id="manage-q" name="q" value="{{ $filters['search'] }}" placeholder="Titolo o luogo">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="manage-status">Stato</label>
                    <select class="form-select" id="manage-status" name="status">
                        <option value="">Tutti</option>
                        @foreach ($statusChoices as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="btn btn-outline-dark mt-3" type="submit">Filtra</button>
        </form>

        <div class="table-responsive shadow-soft overflow-hidden table-card">
            <table class="table align-middle mb-0 bg-white app-table" data-table-responsive>
                <caption class="visually-hidden">Tabella degli eventi gestiti dagli amministratori</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Titolo</th>
                        <th scope="col">Data</th>
                        <th scope="col">Tipologia</th>
                        <th scope="col">Stato</th>
                        <th scope="col">Iscritti</th>
                        <th scope="col" class="text-end">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td data-label="Titolo"><strong>{{ $event->titolo }}</strong><div class="small text-muted">{{ $event->nome_luogo }}</div></td>
                            <td data-label="Data">{{ $event->inizio_il?->format('d/m/Y H:i') }}</td>
                            <td data-label="Tipologia">{{ $event->event_type_label }}</td>
                            <td data-label="Stato">
                                <select class="form-select form-select-sm" data-status-select data-url="{{ route('events.manage-status', $event->id) }}">
                                    @foreach ($statusChoices as $value => $label)
                                        <option value="{{ $value }}" @selected($event->stato === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Iscritti"><a class="table-link" href="{{ route('events.manage-registrants', $event->id) }}">{{ $event->active_registrations_count }}</a></td>
                            <td data-label="Azioni">
                                <div class="d-flex justify-content-end gap-2 table-actions">
                                    <a class="btn btn-outline-dark btn-sm" href="{{ route('events.manage-update', $event->id) }}">Modifica</a>
                                    <a class="btn btn-outline-dark btn-sm" href="{{ route('events.manage-history', $event->id) }}">Storico</a>
                                    <form method="post" action="{{ route('events.manage-delete', $event->id) }}" data-confirm-message="Eliminare definitivamente questo evento?">
                                        @csrf
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Elimina</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">Nessun evento presente.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection

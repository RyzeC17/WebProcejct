@extends('layouts.app')

@section('title', 'Storico modifiche - '.$event->title.' | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end mb-4 section-heading">
            <div>
                <span class="eyebrow">Backoffice amministratore</span>
                <h1 class="display-6 fw-bold mb-1">Storico modifiche</h1>
                <p class="text-muted mb-0">{{ $event->title }}</p>
            </div>
            <a class="btn btn-outline-dark" href="{{ route('events.manage-update', $event->id) }}">Torna alla modifica</a>
        </div>

        @if ($changeLogs->isNotEmpty())
            <div class="table-responsive shadow-soft overflow-hidden table-card">
                <table class="table align-middle mb-0 bg-white app-table" data-table-responsive>
                    <caption class="visually-hidden">Tabella storico modifiche dell'evento {{ $event->title }}</caption>
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Attore</th>
                            <th scope="col">Campi modificati</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($changeLogs as $log)
                            <tr>
                                <td data-label="Data">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td data-label="Attore">{{ $log->actor?->display_name ?? 'Sistema' }}</td>
                                <td data-label="Campi modificati">
                                    @foreach ($log->changed_fields ?? [] as $fieldName => $values)
                                        <div class="changelog-field mb-2">
                                            <strong class="changelog-field-label">{{ $fieldName }}</strong>
                                            <div class="d-flex flex-wrap gap-2 align-items-center small">
                                                <span class="badge text-bg-light changelog-old">{{ $values['old'] ?? '' }}</span>
                                                <span class="text-muted" aria-hidden="true">-></span>
                                                <span class="badge text-bg-dark changelog-new">{{ $values['new'] ?? '' }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="card border-0 shadow-soft">
                <div class="card-body text-center py-5 text-muted">Nessuna modifica registrata per questo evento.</div>
            </div>
        @endif
    </div>
</section>
@endsection

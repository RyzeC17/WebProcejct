@extends('layouts.app')

@section('title', 'Iscritti a '.$event->titolo.' | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 section-heading">
            <div>
                <span class="eyebrow">Backoffice amministratore</span>
                <h1 class="display-6 fw-bold mb-1">Iscritti a {{ $event->titolo }}</h1>
                <p class="text-muted mb-0">{{ $event->inizio_il?->format('d/m/Y H:i') }} &middot; {{ $event->nome_luogo }}</p>
            </div>
            <a class="btn btn-outline-dark" href="{{ route('events.manage-list') }}">Torna alla lista</a>
        </div>

        <div class="table-responsive shadow-soft overflow-hidden table-card">
            <table class="table align-middle mb-0 bg-white app-table" data-table-responsive>
                <caption class="visually-hidden">Tabella degli utenti iscritti all'evento</caption>
                <thead class="table-light">
                    <tr>
                        <th scope="col">Utente</th>
                        <th scope="col">Email</th>
                        <th scope="col">Stato</th>
                        <th scope="col">Nota</th>
                        @foreach ($customFields as $customField)<th scope="col">{{ $customField->etichetta }}</th>@endforeach
                        <th scope="col">Data adesione</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        <tr>
                            <td data-label="Utente">{{ $registration->user->display_name }}</td>
                            <td data-label="Email">{{ $registration->user->email }}</td>
                            <td data-label="Stato">{{ $registration->status_label }}</td>
                            <td data-label="Nota">{{ $registration->nota_partecipante ?: '-' }}</td>
                            @foreach ($registration->custom_answer_pairs as $pair)
                                <td data-label="{{ $pair['label'] }}">{{ $pair['value'] }}</td>
                            @endforeach
                            <td data-label="Data adesione">{{ $registration->creato_il?->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $registrantColumnCount }}" class="text-center py-5 text-muted">Nessuna adesione registrata.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card border-0 shadow-soft mt-4">
            <div class="card-body p-4">
                <h2 class="h4 mb-3">Risposte aggregate</h2>
                <div class="row g-3">
                    @forelse ($aggregateAnswers as $aggregate)
                        <div class="col-12">
                            <article class="aggregate-card">
                                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                    <div>
                                        <h3 class="h5 mb-1">{{ $aggregate['field']->etichetta }}</h3>
                                        <div class="small text-muted">{{ $aggregate['field']->field_type_label }} &middot; {{ $aggregate['count'] }} risposta/e</div>
                                    </div>
                                    <div class="aggregate-content">
                                        @if ($aggregate['field_type'] === 'text')
                                            @forelse ($aggregate['values'] as $item)
                                                <div class="small mb-1"><strong>{{ $item['user_label'] }}:</strong> {{ $item['value'] }}</div>
                                            @empty
                                                <div class="small text-muted">Nessuna risposta disponibile.</div>
                                            @endforelse
                                        @elseif ($aggregate['field_type'] === 'number')
                                            @if ($aggregate['avg'] !== null)
                                                <div class="small">Min: {{ $aggregate['min'] }}</div>
                                                <div class="small">Max: {{ $aggregate['max'] }}</div>
                                                <div class="small">Media: {{ number_format($aggregate['avg'], 2) }}</div>
                                            @else
                                                <div class="small text-muted">Nessuna risposta disponibile.</div>
                                            @endif
                                        @else
                                            @forelse ($aggregate['counts'] as $label => $count)
                                                <div class="small">{{ $label }}: {{ $count }}</div>
                                            @empty
                                                <div class="small text-muted">Nessuna risposta disponibile.</div>
                                            @endforelse
                                        @endif
                                    </div>
                                </div>
                            </article>
                        </div>
                    @empty
                        <div class="col-12"><div class="empty-state py-4">Nessun campo aggiuntivo configurato per questo evento.</div></div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

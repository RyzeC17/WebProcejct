@extends('layouts.app')

@section('title', 'Home | Event Hub')

@section('content')
<section class="hero-section py-5">
    <div class="container py-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="eyebrow">Programmazione Web &middot; Traccia 1</span>
                <h1 class="display-5 fw-bold mt-3">Una piattaforma completa per pubblicare eventi e gestire adesioni.</h1>
                <p class="lead text-muted mt-3">
                    Event Hub permette a enti culturali, associazioni e istituzioni di pubblicare iniziative, raccogliere adesioni e seguire l'intero ciclo di vita degli eventi.
                </p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a class="btn btn-dark btn-lg" href="{{ route('events.list') }}">Esplora gli eventi</a>
                    @guest
                        <a class="btn btn-outline-dark btn-lg" href="{{ route('accounts.register') }}">Crea un account</a>
                    @endguest
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hero-card how-it-works-card shadow-soft p-4">
                    <div class="hiw-orb"></div>
                    <div class="d-flex justify-content-between align-items-start gap-3 how-it-works-header">
                        <div class="min-w-0">
                            <p class="hiw-eyebrow mb-2">Come funziona</p>
                            <h2 class="h4 mb-0">Organizza, pubblica, gestisci</h2>
                        </div>
                        <span class="hiw-badge"><strong>3</strong><span>step</span></span>
                    </div>
                    <ol class="how-it-works-steps list-unstyled mt-4 mb-0">
                        <li class="how-it-works-step hiw-stagger-1">
                            <span class="step-index">1</span>
                            <div><h3 class="h6 mb-1">Crea l'evento</h3><p class="mb-0 text-muted">Dettagli, luogo, date e posti.</p></div>
                        </li>
                        <li class="how-it-works-step hiw-stagger-2">
                            <span class="step-index">2</span>
                            <div><h3 class="h6 mb-1">Pubblica</h3><p class="mb-0 text-muted">Visibile in elenco e calendario.</p></div>
                        </li>
                        <li class="how-it-works-step hiw-stagger-3">
                            <span class="step-index">3</span>
                            <div><h3 class="h6 mb-1">Gestisci adesioni</h3><p class="mb-0 text-muted">Partecipanti, posti e stati.</p></div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 section-heading">
            <div>
                <h2 class="h3 mb-1">Prossimi eventi pubblicati</h2>
                <p class="text-muted mb-0">Una selezione aggiornata automaticamente in base allo stato degli eventi.</p>
            </div>
            <a class="btn btn-outline-dark" href="{{ route('events.list') }}">Vedi tutti</a>
        </div>
        <div class="row g-4">
            @forelse ($featuredEvents as $event)
                <div class="col-md-6 col-xl-4">
                    <article class="card h-100 border-0 shadow-soft card-event featured-card">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="badge text-bg-light">{{ $event->event_type_label }}</span>
                                <span class="status-pill status-{{ $event->operational_state }}">{{ $event->operational_state_label }}</span>
                            </div>
                            <h3 class="h5 mt-3">{{ $event->titolo }}</h3>
                            <p class="text-muted small mb-2">{{ $event->inizio_il?->format('d/m/Y H:i') }} &middot; {{ $event->nome_luogo }}</p>
                            <p class="mb-3">{{ Str::limit($event->descrizione, 120) }}</p>
                            <div class="d-flex justify-content-between align-items-center gap-3 mt-auto">
                                <span class="small text-muted">{{ $event->remaining_seats }} posti disponibili</span>
                                <a class="btn btn-sm btn-outline-dark" href="{{ route('events.detail', $event->slug) }}">Apri dettaglio</a>
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="empty-state">Nessun evento pubblicato al momento.</div></div>
            @endforelse
        </div>
    </div>
</section>
@endsection

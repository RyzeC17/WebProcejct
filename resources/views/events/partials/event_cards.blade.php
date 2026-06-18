<div class="row g-4">
    @forelse ($events as $event)
        <div class="col-md-6 col-xl-4">
            <article class="card h-100 border-0 shadow-soft card-event">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="badge text-bg-light">{{ $event->event_type_label }}</span>
                        <span class="status-pill status-{{ $event->operational_state }}">{{ $event->operational_state_label }}</span>
                    </div>
                    <h2 class="h5 mt-3">{{ $event->title }}</h2>
                    <p class="small text-muted mb-1">{{ $event->start_datetime?->format('d/m/Y H:i') }} &middot; {{ $event->venue_name }}</p>
                    <p class="mb-3">{{ Str::limit($event->description, 130) }}</p>
                    <div class="small text-muted mb-3 event-meta-list">
                        <div class="event-meta-item"><span class="event-meta-label">Posti</span><strong>{{ $event->remaining_seats }}</strong></div>
                        <div class="event-meta-item"><span class="event-meta-label">Scadenza</span><strong>{{ $event->registration_deadline?->format('d/m/Y H:i') }}</strong></div>
                    </div>
                    <div class="mt-auto d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-semibold">{{ (float) $event->price > 0 ? 'EUR '.$event->price : 'Gratuito' }}</span>
                        <a class="btn btn-outline-dark" href="{{ route('events.detail', $event->slug) }}">Dettaglio</a>
                    </div>
                </div>
            </article>
        </div>
    @empty
        <div class="col-12">
            <div class="empty-state">Nessun evento trovato con i filtri selezionati.</div>
        </div>
    @endforelse
</div>

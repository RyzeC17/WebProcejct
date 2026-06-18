@extends('layouts.app')

@section('title', 'Le mie adesioni | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 section-heading">
            <div>
                <span class="eyebrow">Area personale</span>
                <h1 class="display-6 fw-bold mb-1">Le mie adesioni</h1>
                <p class="text-muted mb-0">Visualizza, aggiorna o annulla la partecipazione agli eventi.</p>
            </div>
        </div>

        <div class="row g-4">
            @forelse ($registrations as $registration)
                <div class="col-12">
                    <article class="card border-0 shadow-soft registration-card" data-feedback-context>
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div>
                                    <h2 class="h4">{{ $registration->event->title }}</h2>
                                    <p class="text-muted mb-1">{{ $registration->event->start_datetime?->format('d/m/Y H:i') }} &middot; {{ $registration->event->venue_name }}</p>
                                    <span class="status-pill status-{{ $registration->event->operational_state }}">{{ $registration->event->operational_state_label }}</span>
                                    <span class="badge text-bg-light">{{ $registration->status_label }}</span>
                                </div>
                                <div class="text-lg-end">
                                    <a class="btn btn-outline-dark btn-sm" href="{{ route('events.detail', $registration->event->slug) }}">Apri evento</a>
                                </div>
                            </div>

                            <div class="ajax-feedback small mt-3" aria-live="polite"></div>

                            <div class="row g-3 mt-2">
                                <div class="col-lg-8">
                                    <form data-json-form action="{{ route('events.update-registration', $registration->id) }}" method="post">
                                        @csrf
                                        <label class="form-label" for="note-{{ $registration->id }}">Nota personale</label>
                                        <textarea class="form-control" id="note-{{ $registration->id }}" name="attendee_note" rows="3" @disabled($registration->status === 'cancelled')>{{ $registration->attendee_note }}</textarea>
                                        <button class="btn btn-outline-dark mt-3" type="submit" @disabled($registration->status === 'cancelled') data-loading-label="Aggiornamento...">Aggiorna nota</button>
                                    </form>
                                </div>
                                <div class="col-lg-4 d-flex align-items-end">
                                    <form data-json-form action="{{ route('events.cancel-registration', $registration->id) }}" method="post" class="w-100">
                                        @csrf
                                        <button class="btn btn-outline-danger w-100" type="submit" @disabled($registration->status === 'cancelled') data-loading-label="Annullamento...">Annulla adesione</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="empty-state">Non hai ancora aderito a nessun evento.</div></div>
            @endforelse
        </div>
    </div>
</section>
@endsection

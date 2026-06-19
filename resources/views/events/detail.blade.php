@extends('layouts.app')

@section('title', $event->titolo.' | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-8">
                <article class="card border-0 shadow-soft detail-card">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <span class="badge text-bg-light">{{ $event->event_type_label }}</span>
                            <span class="status-pill status-{{ $event->operational_state }}">{{ $event->operational_state_label }}</span>
                        </div>
                        <h1 class="display-6 fw-bold">{{ $event->titolo }}</h1>
                        <p class="lead text-muted">{{ $event->descrizione }}</p>

                        <div class="row g-3 event-metrics mt-1 mb-4">
                            <div class="col-sm-4"><div class="event-metric"><span class="event-metric-label">Posti disponibili</span><strong>{{ $event->remaining_seats }} / {{ $event->max_partecipanti }}</strong></div></div>
                            <div class="col-sm-4"><div class="event-metric"><span class="event-metric-label">Costo</span><strong>{{ (float) $event->prezzo > 0 ? 'EUR '.$event->prezzo : 'Gratuito' }}</strong></div></div>
                            <div class="col-sm-4"><div class="event-metric"><span class="event-metric-label">Scadenza iscrizioni</span><strong>{{ $event->scadenza_iscrizioni?->format('d/m/Y H:i') }}</strong></div></div>
                        </div>

                        <dl class="row mt-4 mb-0 detail-definition">
                            <dt class="col-sm-4">Luogo</dt>
                            <dd class="col-sm-8">{{ $event->nome_luogo }}<br>{!! nl2br(e($event->indirizzo_luogo)) !!}</dd>
                            <dt class="col-sm-4">Inizio</dt>
                            <dd class="col-sm-8">{{ $event->inizio_il?->format('d/m/Y H:i') }}</dd>
                            <dt class="col-sm-4">Fine</dt>
                            <dd class="col-sm-8">{{ $event->fine_il?->format('d/m/Y H:i') }}</dd>
                            @if ($event->note)
                                <dt class="col-sm-4">Note</dt>
                                <dd class="col-sm-8">{!! nl2br(e($event->note)) !!}</dd>
                            @endif
                        </dl>
                    </div>
                </article>

                @if ($event->stato === 'completed' && isset($feedbackSummary))
                    <div class="card border-0 shadow-soft mt-4 feedback-section" id="feedback-section">
                        <div class="card-body p-4 p-lg-5">
                            <h2 class="h4 fw-bold mb-4">Feedback dei partecipanti</h2>
                            <div class="row g-4 mb-4">
                                <div class="col-sm-4 text-center">
                                    <div class="feedback-average">
                                        @if ($feedbackSummary['average_rating'])
                                            <span class="feedback-average-number">{{ $feedbackSummary['average_rating'] }}</span>
                                            <span class="feedback-average-max text-muted">/ 5</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted mt-1">{{ $feedbackSummary['review_count'] }} recensioni</div>
                                </div>
                                <div class="col-sm-8">
                                    @foreach ($feedbackSummary['rating_distribution'] as $starVal => $starCount)
                                        @php $width = $feedbackSummary['review_count'] > 0 ? round(($starCount / $feedbackSummary['review_count']) * 100) : 0; @endphp
                                        <div class="d-flex align-items-center gap-2 mb-1 rating-bar-row">
                                            <span class="small fw-semibold rating-bar-label">{{ $starVal }} *</span>
                                            <div class="rating-bar flex-grow-1"><div class="rating-bar-fill" style="width: {{ $width }}%" aria-hidden="true"></div></div>
                                            <span class="small text-muted rating-bar-count">{{ $starCount }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            @if (!empty($canLeaveFeedback))
                                <div class="border-top pt-4 mt-2">
                                    <h3 class="h5 mb-3">{{ $userFeedback ? 'Aggiorna il tuo feedback' : 'Lascia il tuo feedback' }}</h3>
                                    <div data-feedback-context>
                                        <form data-json-form action="{{ route('events.submit-feedback', $event->slug) }}" method="post">
                                            @csrf
                                            <div id="feedback-form-feedback" class="ajax-feedback small mb-3" aria-live="polite"></div>
                                            <div class="form-group sidebar-form-group">
                                                <label class="form-label" for="rating">Voto</label>
                                                <select class="form-select" id="rating" name="rating" required>
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <option value="{{ $i }}" @selected((int) old('rating', $userFeedback?->valutazione ?? 5) === $i)>{{ $i }} {{ $i === 1 ? 'stella' : 'stelle' }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div class="form-group sidebar-form-group mt-3">
                                                <label class="form-label" for="comment">Commento</label>
                                                <textarea class="form-control" id="comment" name="comment" rows="3" maxlength="1000" placeholder="Lascia un commento (facoltativo)">{{ old('comment', $userFeedback?->commento) }}</textarea>
                                            </div>
                                            <button class="btn btn-dark mt-3" type="submit" data-loading-label="Invio...">Invia feedback</button>
                                        </form>
                                    </div>
                                </div>
                            @endif

                            @if ($feedbacks->isNotEmpty())
                                <div class="border-top pt-4 mt-4">
                                    <h3 class="h5 mb-3">Recensioni</h3>
                                    @foreach ($feedbacks as $fb)
                                        <div class="feedback-item mb-3 pb-3 {{ $loop->last ? '' : 'border-bottom' }}">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <strong class="small">{{ $fb->user->display_name }}</strong>
                                                <span class="small text-muted">{{ $fb->creato_il?->format('d/m/Y H:i') }}</span>
                                            </div>
                                            <div class="feedback-stars mb-1">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <span class="{{ $i <= $fb->valutazione ? 'star-filled' : 'star-empty' }}">{{ $i <= $fb->valutazione ? '*' : '-' }}</span>
                                                @endfor
                                            </div>
                                            @if ($fb->commento)<p class="small text-muted mb-0">{{ $fb->commento }}</p>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-soft event-side-panel">
                    <div class="card-body p-4">
                        <p class="small text-uppercase fw-semibold text-muted mb-2">Azioni rapide</p>
                        @if ($event->stato === 'completed')
                            <h2 class="h4">Evento completato</h2>
                            <div class="alert alert-secondary app-alert mb-0">Questo evento si e concluso.</div>
                        @else
                            <h2 class="h4">Partecipa all'evento</h2>
                            @guest
                                <p class="text-muted">Accedi o registrati per aderire all'evento.</p>
                                <a class="btn btn-dark w-100" href="{{ route('accounts.login') }}">Accedi</a>
                            @else
                                <div class="sidebar-form-stack" data-feedback-context>
                                    <div id="event-action-feedback" class="ajax-feedback small mb-3" aria-live="polite"></div>
                                    @if ($registration && $registration->stato === 'active')
                                        <div class="alert alert-success app-alert">Risulti iscritto a questo evento.</div>
                                        @include('events.partials.registration_actions', ['registration' => $registration, 'cancelLabel' => 'Annulla adesione'])
                                    @elseif ($registration && $registration->stato === 'waitlisted')
                                        <div class="alert alert-info app-alert">Sei in lista d'attesa per questo evento.</div>
                                        @include('events.partials.registration_actions', ['registration' => $registration, 'cancelLabel' => "Rinuncia alla lista d'attesa"])
                                    @elseif ($event->accepts_new_requests)
                                        <form data-json-form action="{{ route('events.register', $event->slug) }}" method="post">
                                            @csrf
                                            <div class="form-group sidebar-form-group">
                                                <label class="form-label" for="attendee_note">Nota facoltativa</label>
                                                <textarea class="form-control" id="attendee_note" name="attendee_note" rows="3" maxlength="500" placeholder="Eventuali note per gli organizzatori"></textarea>
                                            </div>
                                            @foreach ($event->customFields as $field)
                                                <div class="form-group sidebar-form-group mt-3">
                                                    <label class="form-label" for="custom_field_{{ $field->id }}">{{ $field->etichetta }}</label>
                                                    @if ($field->tipo_campo === 'text')
                                                        <input class="form-control" id="custom_field_{{ $field->id }}" name="custom_field_{{ $field->id }}" type="text" maxlength="255" @required($field->obbligatorio)>
                                                    @elseif ($field->tipo_campo === 'number')
                                                        <input class="form-control" id="custom_field_{{ $field->id }}" name="custom_field_{{ $field->id }}" type="number" step="any" @required($field->obbligatorio)>
                                                    @elseif ($field->tipo_campo === 'boolean')
                                                        <select class="form-select" id="custom_field_{{ $field->id }}" name="custom_field_{{ $field->id }}" @required($field->obbligatorio)>
                                                            <option value="">Seleziona</option>
                                                            <option value="true">Si</option>
                                                            <option value="false">No</option>
                                                        </select>
                                                    @else
                                                        <select class="form-select" id="custom_field_{{ $field->id }}" name="custom_field_{{ $field->id }}" @required($field->obbligatorio)>
                                                            <option value="">Seleziona</option>
                                                            @foreach ($field->options as $option)
                                                                <option value="{{ $option->id }}">{{ $option->valore }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>
                                            @endforeach
                                            <button class="btn btn-dark w-100 mt-3" type="submit" data-loading-label="Invio iscrizione...">
                                                {{ $event->remaining_seats > 0 ? 'Iscriviti ora' : "Entra in lista d'attesa" }}
                                            </button>
                                        </form>
                                    @else
                                        <div class="alert alert-warning mb-0 app-alert">Le iscrizioni non sono disponibili per questo evento.</div>
                                    @endif
                                </div>
                            @endguest
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

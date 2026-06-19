@extends('layouts.app')

@section('title', 'Notifiche | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4 section-heading">
            <div>
                <span class="eyebrow">Area personale</span>
                <h1 class="display-6 fw-bold mb-1">Storico notifiche</h1>
                <p class="text-muted mb-0">Hai {{ $unreadCount }} notifiche non lette.</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-dark" type="button" data-notification-mark-all-url="{{ route('notifications.mark-all-read') }}">Segna tutte come lette</button>
                <a class="btn btn-outline-dark" href="{{ route('events.my-registrations') }}">Torna alle adesioni</a>
            </div>
        </div>

        <div class="notification-feed notification-feed-page">
            @forelse ($notifications as $notification)
                <article class="card border-0 shadow-soft notification-item {{ $notification->letta ? '' : 'is-unread' }}" data-notification-item data-notification-id="{{ $notification->id }}">
                    <div class="card-body p-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                            <div>
                                <div class="small text-muted mb-2">{{ $notification->notification_type_label }}</div>
                                <p class="mb-2">{{ $notification->testo }}</p>
                                <div class="small text-muted">{{ $notification->creato_il?->format('d/m/Y H:i') }}</div>
                            </div>
                            <div class="d-flex flex-column align-items-lg-end gap-2">
                                <a class="btn btn-outline-dark btn-sm" href="{{ $notification->target_url }}">Apri riferimento</a>
                                <button class="btn btn-outline-dark btn-sm" type="button" data-notification-mark-read-url="{{ route('notifications.mark-read', $notification->id) }}" @disabled($notification->letta)>
                                    Segna come letta
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="empty-state">Non hai ancora ricevuto notifiche.</div>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    </div>
</section>
@endsection

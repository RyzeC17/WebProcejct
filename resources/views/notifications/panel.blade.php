<div class="notification-panel">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
        <div>
            <p class="small text-muted mb-1">Notifiche non lette: {{ $unreadCount }}</p>
            <p class="small text-muted mb-0">Gli aggiornamenti sono visibili solo al destinatario.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-dark btn-sm" href="{{ route('notifications.list') }}">Storico completo</a>
            <button class="btn btn-dark btn-sm" type="button" data-notification-mark-all-url="{{ route('notifications.mark-all-read') }}">
                Segna tutte come lette
            </button>
        </div>
    </div>

    <div class="notification-feed">
        @forelse ($notifications as $notification)
            <article class="notification-item {{ $notification->is_read ? '' : 'is-unread' }}" data-notification-item data-notification-id="{{ $notification->id }}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <p class="mb-1">{{ $notification->text }}</p>
                        <div class="small text-muted">{{ $notification->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    <button class="btn btn-outline-dark btn-sm" type="button" data-notification-mark-read-url="{{ route('notifications.mark-read', $notification->id) }}" @disabled($notification->is_read)>
                        Letta
                    </button>
                </div>
                <a class="notification-link" href="{{ $notification->target_url }}">Apri riferimento</a>
            </article>
        @empty
            <div class="empty-state py-4">Non ci sono notifiche da mostrare.</div>
        @endforelse
    </div>
</div>

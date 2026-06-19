<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Event Hub')</title>
    @php
        $versionedAsset = static function (string $path): string {
            $absolutePath = public_path($path);

            return asset($path).(is_file($absolutePath) ? '?v='.filemtime($absolutePath) : '');
        };
    @endphp
    <link rel="stylesheet" href="{{ $versionedAsset('static/vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ $versionedAsset('static/css/app.css') }}">
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#main-content">Salta al contenuto principale</a>
    @php
        $notificationUnreadCount = auth()->check()
            ? auth()->user()->notifications()->where('letta', false)->count()
            : 0;
    @endphp

    <header class="site-header border-bottom sticky-top">
        <nav class="navbar navbar-expand-lg navbar-light py-2">
            <div class="container py-2">
                <a class="navbar-brand fw-bold tracking-wide" href="{{ route('home') }}">
                    <span class="brand-mark">EH</span>
                    <span>Event Hub</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
                    aria-controls="navbarMain" aria-expanded="false" aria-label="Apri navigazione">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 nav-links-cluster">
                        <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('events.list') }}">Eventi</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ route('events.calendar') }}">Calendario</a></li>
                        @auth
                            <li class="nav-item"><a class="nav-link" href="{{ route('events.my-registrations') }}">Le mie adesioni</a></li>
                        @endauth
                        @role('admin')
                            <li class="nav-item"><a class="nav-link" href="{{ route('events.manage-list') }}">Gestione eventi</a></li>
                        @endrole
                    </ul>
                    <div class="d-flex align-items-center gap-2 nav-actions">
                        @auth
                            <button
                                class="btn btn-outline-dark btn-sm notification-trigger"
                                type="button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#notificationPanel"
                                aria-controls="notificationPanel"
                                aria-label="Apri pannello notifiche"
                                data-notification-panel-url="{{ route('notifications.panel') }}"
                                data-notification-summary-url="{{ route('notifications.summary') }}"
                            >
                                <span class="notification-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6v-5a7 7 0 0 0-14 0v5l-2 2v1h18v-1l-2-2Z" />
                                    </svg>
                                </span>
                                <span id="notification-badge" class="notification-badge {{ $notificationUnreadCount === 0 ? 'is-hidden' : '' }}">
                                    {{ $notificationUnreadCount }}
                                </span>
                            </button>
                            <span class="user-chip small fw-semibold">{{ auth()->user()->display_name }}</span>
                            <form method="post" action="{{ route('accounts.logout') }}" class="m-0">
                                @csrf
                                <button class="btn btn-outline-dark btn-sm" type="submit">Esci</button>
                            </form>
                        @else
                            <a class="btn btn-outline-dark btn-sm" href="{{ route('accounts.login') }}">Accedi</a>
                            <a class="btn btn-dark btn-sm" href="{{ route('accounts.register') }}">Registrati</a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <div id="ui-feedback-stack" class="ui-feedback-stack" aria-live="polite" aria-atomic="true"></div>
    @include('partials.messages')

    <main id="main-content" class="page-shell" tabindex="-1">
        @yield('content')
    </main>

    @auth
        <div class="offcanvas offcanvas-end notification-offcanvas" tabindex="-1" id="notificationPanel" aria-labelledby="notificationPanelLabel">
            <div class="offcanvas-header">
                <h2 class="h5 offcanvas-title" id="notificationPanelLabel">Notifiche</h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Chiudi"></button>
            </div>
            <div class="offcanvas-body" id="notification-panel-body">
                <div class="notification-loading text-muted small">Caricamento notifiche in corso...</div>
            </div>
        </div>
    @endauth

    <footer class="site-footer border-top py-4 mt-5 bg-white">
        <div class="container text-center small text-muted">&copy; Event Hub</div>
    </footer>

    <script src="{{ $versionedAsset('static/vendor/jquery.min.js') }}"></script>
    <script src="{{ $versionedAsset('static/vendor/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ $versionedAsset('static/js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>

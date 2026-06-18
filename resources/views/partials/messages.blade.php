@php
    $flashes = ['success' => 'success', 'status' => 'info', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
@endphp

@if ($errors->any() || collect(array_keys($flashes))->contains(fn ($key) => session()->has($key)))
    <div class="container pt-4 messages-wrap">
        @foreach ($flashes as $key => $type)
            @if (session()->has($key))
                <div class="alert alert-{{ $type }} alert-dismissible fade show app-alert" role="alert">
                    {{ session($key) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
                </div>
            @endif
        @endforeach
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show app-alert" role="alert">
                Controlla i campi evidenziati.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Chiudi"></button>
            </div>
        @endif
    </div>
@endif

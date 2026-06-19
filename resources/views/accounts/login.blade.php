@extends('layouts.app')

@section('title', 'Accedi | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="card border-0 shadow-soft auth-panel">
                    <div class="card-body p-4 p-lg-5">
                        <span class="eyebrow">Accesso utente</span>
                        <h1 class="h3 mb-3">Accedi</h1>
                        <p class="text-muted">Entra per aderire agli eventi e gestire la tua area personale.</p>
                        <form method="post" action="{{ route('accounts.login.store') }}" novalidate>
                            @csrf
                            <div class="mb-3 field-group form-group">
                                <label class="form-label" for="username">Username o email</label>
                                <input class="form-control @error('username') is-invalid @enderror" type="text" name="username" id="username" value="{{ old('username') }}" autocomplete="username" required>
                                @error('username')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3 field-group form-group">
                                <label class="form-label" for="password">Password</label>
                                <input class="form-control @error('password') is-invalid @enderror" type="password" name="password" id="password" autocomplete="current-password" required>
                                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-dark w-100" type="submit">Accedi</button>
                        </form>
                        <p class="small text-muted mt-3 mb-0">Non hai ancora un account? <a href="{{ route('accounts.register') }}">Registrati</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

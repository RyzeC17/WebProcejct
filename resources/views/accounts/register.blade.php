@extends('layouts.app')

@section('title', 'Registrati | Event Hub')

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-0 shadow-soft auth-panel">
                    <div class="card-body p-4 p-lg-5">
                        <span class="eyebrow">Nuovo account</span>
                        <h1 class="h3 mb-3">Crea un account</h1>
                        <p class="text-muted">Con un profilo registrato puoi aderire agli eventi e gestire le tue adesioni.</p>
                        <form method="post" novalidate>
                            @csrf
                            <div class="row g-3">
                                @foreach ([
                                    'first_name' => ['Nome', 'text', 'given-name'],
                                    'last_name' => ['Cognome', 'text', 'family-name'],
                                    'username' => ['Username', 'text', 'username'],
                                    'email' => ['Email', 'email', 'email'],
                                    'password' => ['Password', 'password', 'new-password'],
                                    'password_confirmation' => ['Conferma password', 'password', 'new-password'],
                                ] as $name => [$label, $type, $autocomplete])
                                    <div class="col-md-6 field-group form-group">
                                        <label class="form-label" for="{{ $name }}">{{ $label }}</label>
                                        <input class="form-control @error($name) is-invalid @enderror" type="{{ $type }}" name="{{ $name }}" id="{{ $name }}" value="{{ $type === 'password' ? '' : old($name) }}" autocomplete="{{ $autocomplete }}" required>
                                        @error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endforeach
                            </div>
                            <button class="btn btn-dark mt-4" type="submit">Registrati</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

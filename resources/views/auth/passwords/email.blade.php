@extends('layouts.auth', ['title' => 'Esqueci minha senha'])

@section('auth')
    <p class="login-box-msg">
        <strong class="d-block mb-1">Esqueci minha senha</strong>
        Informe seu e-mail e enviaremos um link para criar uma nova senha.
    </p>

    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-3">
            <div class="input-group has-validation">
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror" placeholder="E-mail"
                    autocomplete="email" required autofocus>
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Enviar link</button>
        </div>
    </form>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </p>
@endsection

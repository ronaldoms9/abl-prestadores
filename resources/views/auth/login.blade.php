@extends('layouts.auth', ['title' => 'Entrar'])

@section('auth')
    <p class="login-box-msg">Entre para acessar o sistema</p>

    @if (session('status'))
        <div class="alert alert-success" role="alert">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="mb-3">
            <div class="input-group has-validation">
                <input type="email" name="email" id="email" value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror" placeholder="E-mail"
                    autocomplete="username" required autofocus>
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <div class="input-group has-validation">
                <input type="password" name="password" id="password"
                    class="form-control @error('password') is-invalid @enderror" placeholder="Senha"
                    autocomplete="current-password" required>
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row align-items-center">
            <div class="col-7">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember"
                        @checked(old('remember'))>
                    <label class="form-check-label" for="remember">Lembrar-me</label>
                </div>
            </div>
            <div class="col-5 d-grid">
                <button type="submit" class="btn btn-primary">Entrar</button>
            </div>
        </div>
    </form>

    @if (Route::has('password.request'))
        <p class="mt-3 mb-0">
            <a href="{{ route('password.request') }}">Esqueci minha senha</a>
        </p>
    @endif
@endsection

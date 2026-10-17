@extends('layouts.auth', ['title' => 'Redefinir senha'])

@section('auth')
    <p class="login-box-msg">
        <strong class="d-block mb-1">Redefinir senha</strong>
        Escolha uma nova senha com pelo menos 8 caracteres.
    </p>

    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <div class="input-group has-validation">
                <input type="email" name="email" id="email" value="{{ $email ?? old('email') }}"
                    class="form-control @error('email') is-invalid @enderror" placeholder="E-mail"
                    autocomplete="username" required>
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <div class="input-group has-validation">
                <input type="password" name="password" id="password"
                    class="form-control @error('password') is-invalid @enderror" placeholder="Nova senha"
                    autocomplete="new-password" required autofocus>
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <div class="input-group">
                <input type="password" name="password_confirmation" id="password_confirmation"
                    class="form-control" placeholder="Confirme a nova senha" autocomplete="new-password" required>
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Redefinir senha</button>
        </div>
    </form>

    <p class="mt-3 mb-0">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </p>
@endsection

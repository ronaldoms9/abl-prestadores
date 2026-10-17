{{--
    Telas de visitante (login e recuperação de senha): caixa central no padrão
    login-box do AdminLTE 4. O layout app já põe a classe login-page no <body>.
--}}
@extends('layouts.app')

@section('content')
    <div class="login-box">
        <div class="login-logo">
            @if (file_exists(public_path('images/logo-login.png')))
                <img src="{{ asset('images/logo-login.png') }}" alt="{{ config('app.name') }}" class="img-fluid">
            @else
                <h1 class="h3 mb-0">{{ config('app.name') }}</h1>
            @endif
        </div>
        <div class="card card-outline card-primary">
            <div class="card-body login-card-body">
                @yield('auth')
            </div>
        </div>
    </div>
@endsection

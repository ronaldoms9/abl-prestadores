@extends('layouts.app', ['title' => 'Confirme seu e-mail'])

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card card-outline card-primary">
                <div class="card-body">
                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            Enviamos um novo link de confirmação para o seu e-mail.
                        </div>
                    @endif

                    <p>Antes de continuar, abra o link de confirmação que enviamos para o seu e-mail.</p>

                    <form method="POST" action="{{ route('verification.resend') }}" class="d-inline">
                        @csrf
                        Não recebeu?
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">Enviar outro link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

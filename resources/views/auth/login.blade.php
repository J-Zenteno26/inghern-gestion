@extends("layouts.guest")
@section("title", "Ingresar")
@section("content")
    <h1 class="auth-heading">Bienvenido de vuelta</h1>
    <p class="auth-copy">
        Ingresa con tu cuenta para continuar a la operación.
    </p>
    @if (session("status"))
        <div class="flash">{{ session("status") }}</div>
    @endif

    @if ($errors->any())
        <div class="flash flash--error">
            Las credenciales no son correctas o la cuenta está desactivada.
        </div>
    @endif

    <form class="auth-form" method="POST" action="{{ route("login") }}">
        @csrf
        <x-ui.field name="email" label="Correo electrónico" required>
            <x-ui.input
                name="email"
                type="email"
                autocomplete="email"
                autofocus
                required
                placeholder="nombre@empresa.cl"
            />
        </x-ui.field>
        <x-ui.field name="password" label="Contraseña" required>
            <x-ui.input
                name="password"
                type="password"
                autocomplete="current-password"
                required
            />
        </x-ui.field>
        <div class="cluster cluster--spread">
            <x-ui.checkbox name="remember" label="Mantener sesión" />
            @if (Route::has("password.request"))
                <a class="auth-link" href="{{ route("password.request") }}">
                    Olvidé mi contraseña
                </a>
            @endif
        </div>
        <x-ui.button type="submit">
            Ingresar a INGHERN
            <x-ui.icon name="arrow" />
        </x-ui.button>
    </form>
@endsection

@extends("layouts.guest")
@section("title", "Recuperar acceso")
@section("content")
    <h1 class="auth-heading">Recuperar acceso</h1>
    <p class="auth-copy">
        Te enviaremos un enlace para definir una nueva contraseña.
    </p>
    @if (session("status"))
        <div class="flash">{{ session("status") }}</div>
    @endif

    <form
        class="auth-form"
        method="POST"
        action="{{ route("password.email") }}"
    >
        @csrf
        <x-ui.field name="email" label="Correo electrónico" required>
            <x-ui.input
                name="email"
                type="email"
                autocomplete="email"
                required
            />
        </x-ui.field>
        <x-ui.button type="submit">Enviar enlace</x-ui.button>
        <a class="auth-link" href="{{ route("login") }}">Volver al ingreso</a>
    </form>
@endsection

@extends("layouts.guest")
@section("title", "Verificación")
@section("content")
    <h1 class="auth-heading">Verificación en dos pasos</h1>
    <p class="auth-copy">
        Ingresa el código de tu aplicación autenticadora o uno de tus códigos de
        recuperación.
    </p>
    <form
        class="auth-form"
        method="POST"
        action="{{ route("two-factor.login") }}"
    >
        @csrf
        <x-ui.field name="code" label="Código de autenticación">
            <x-ui.input
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
            />
        </x-ui.field>
        <x-ui.field name="recovery_code" label="Código de recuperación">
            <x-ui.input name="recovery_code" autocomplete="one-time-code" />
        </x-ui.field>
        <x-ui.button type="submit">Verificar acceso</x-ui.button>
    </form>
@endsection

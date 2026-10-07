@extends("layouts.guest")
@section("title", "Nueva contraseña")
@section("content")
    <h1 class="auth-heading">Define tu contraseña</h1>
    <p class="auth-copy">Usa al menos 12 caracteres para proteger tu cuenta.</p>
    <form
        class="auth-form"
        method="POST"
        action="{{ route("password.update") }}"
    >
        @csrf
        <input
            type="hidden"
            name="token"
            value="{{ $request->route("token") }}"
        />
        <x-ui.field name="email" label="Correo electrónico" required>
            <x-ui.input
                name="email"
                type="email"
                :value="$request->email"
                required
            />
        </x-ui.field>
        <x-ui.field name="password" label="Nueva contraseña" required>
            <x-ui.input
                name="password"
                type="password"
                autocomplete="new-password"
                required
            />
        </x-ui.field>
        <x-ui.field
            name="password_confirmation"
            label="Confirmar contraseña"
            required
        >
            <x-ui.input
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                required
            />
        </x-ui.field>
        <x-ui.button type="submit">Guardar contraseña</x-ui.button>
    </form>
@endsection

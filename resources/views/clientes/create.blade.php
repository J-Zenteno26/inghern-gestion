@extends("layouts.app")
@section("title", "Nuevo cliente")
@section("content")
    <x-ui.page-header
        eyebrow="Relaciones"
        title="Nuevo cliente"
        description="Construye una ficha única para evitar duplicar información en servicios y propuestas."
    />
    <form method="POST" action="{{ route("clientes.store") }}">
        @csrf
        @include("clientes._form")
    </form>
@endsection

@extends("layouts.app")
@section("title", "Editar cliente")
@section("content")
    <x-ui.page-header
        eyebrow="Relaciones"
        :title="'Editar '.$cliente->nombre_display"
    />
    <form method="POST" action="{{ route("clientes.update", $cliente) }}">
        @csrf
        @method("PUT")
        @include("clientes._form")
    </form>
@endsection

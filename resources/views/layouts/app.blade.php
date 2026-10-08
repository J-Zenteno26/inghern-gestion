<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>@yield("title", "Inicio") · INGHERN</title>
        <link rel="icon" href="{{ asset("images/brand/favicon-32.png") }}" />
        <link
            rel="apple-touch-icon"
            href="{{ asset("images/brand/apple-touch-icon.png") }}"
        />
        @vite(["resources/css/app.css", "resources/js/app.js"])
    </head>
    <body>
        <div class="app-shell">
            <header class="app-topbar">
                <div class="app-topbar__inner">
                    <a
                        class="app-brand"
                        href="{{ route("dashboard") }}"
                        aria-label="INGHERN, inicio"
                    >
                        <img
                            src="{{ asset("images/brand/inghern-logo.png") }}"
                            alt="INGHERN"
                        />
                    </a>
                    <button
                        class="app-menu-toggle"
                        type="button"
                        data-menu-toggle
                        aria-label="Abrir navegación"
                    >
                        <x-ui.icon name="menu" size="21" />
                    </button>
                    <nav class="app-nav" data-main-menu>
                        <a
                            class="app-nav__link {{ request()->routeIs("dashboard") ? "is-active" : "" }}"
                            href="{{ route("dashboard") }}"
                        >
                            Inicio
                        </a>
                        <a
                            class="app-nav__link {{ request()->routeIs("plantas.*") ? "is-active" : "" }}"
                            href="{{ route("plantas.index") }}"
                        >
                            Plantas
                        </a>
                        <a
                            class="app-nav__link {{ request()->routeIs("servicios.*") ? "is-active" : "" }}"
                            href="{{ route("servicios.index") }}"
                        >
                            Operación
                        </a>
                        <a
                            class="app-nav__link {{ request()->routeIs("cotizaciones.*", "facturas.*", "pagos.*") ? "is-active" : "" }}"
                            href="{{ route("cotizaciones.index") }}"
                        >
                            Comercial
                        </a>
                        <a
                            class="app-nav__link {{ request()->routeIs("biblioteca.*") ? "is-active" : "" }}"
                            href="{{ route("biblioteca.index") }}"
                        >
                            Biblioteca
                        </a>
                        <a
                            class="app-nav__link {{ request()->routeIs("clientes.*") ? "is-active" : "" }}"
                            href="{{ route("clientes.index") }}"
                        >
                            Organizaciones
                        </a>
                    </nav>
                    <div class="app-account">
                        <div class="app-account__meta">
                            <span class="app-account__name">
                                {{ auth()->user()->name }}
                            </span>
                            <span class="app-account__role">
                                {{ auth()->user()->rol }}
                            </span>
                        </div>
                        <div class="app-account__avatar">
                            {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <form method="POST" action="{{ route("logout") }}">
                            @csrf
                            <button
                                class="ui-button ui-button--ghost ui-button--icon"
                                type="submit"
                                title="Cerrar sesión"
                            >
                                <x-ui.icon name="logout" />
                            </button>
                        </form>
                    </div>
                </div>
            </header>
            @if (request()->routeIs("plantas.*", "servicios.*", "cotizaciones.*", "facturas.*", "pagos.*", "clientes.*"))
                <div class="module-bar">
                    <nav class="module-bar__inner">
                        <span class="module-bar__label">
                            {{ request()->routeIs("plantas.*") ? "Plantas" : (request()->routeIs("servicios.*") ? "Operación" : (request()->routeIs("cotizaciones.*", "facturas.*", "pagos.*") ? "Comercial" : "Organizaciones")) }}
                        </span>

                        @if (request()->routeIs("plantas.*"))
                            <a class="module-bar__link is-active" href="{{ route("plantas.index") }}">Centros de control</a>
                        @elseif (request()->routeIs("servicios.*"))
                            <a
                                class="module-bar__link {{ request()->routeIs("servicios.index") ? "is-active" : "" }}"
                                href="{{ route("servicios.index") }}"
                            >
                                Servicios
                            </a>
                            <a
                                class="module-bar__link {{ request()->routeIs("servicios.create") ? "is-active" : "" }}"
                                href="{{ route("servicios.create") }}"
                            >
                                Nuevo servicio
                            </a>
                            <a
                                class="module-bar__link {{ request()->routeIs("servicios.referencias-precio.*") ? "is-active" : "" }}"
                                href="{{ route("servicios.referencias-precio.index") }}"
                            >
                                Referencias de precio
                            </a>
                        @elseif (request()->routeIs("cotizaciones.*", "facturas.*", "pagos.*"))
                            <a
                                class="module-bar__link {{ request()->routeIs("cotizaciones.index") ? "is-active" : "" }}"
                                href="{{ route("cotizaciones.index") }}"
                            >
                                Cotizaciones
                            </a>
                            <a
                                class="module-bar__link {{ request()->routeIs("cotizaciones.create") ? "is-active" : "" }}"
                                href="{{ route("cotizaciones.create") }}"
                            >
                                Nueva cotización
                            </a>
                            <a
                                class="module-bar__link {{ request()->routeIs("facturas.*") ? "is-active" : "" }}"
                                href="{{ route("facturas.index") }}"
                            >
                                Facturas
                            </a>
                            <a
                                class="module-bar__link {{ request()->routeIs("pagos.*") ? "is-active" : "" }}"
                                href="{{ route("pagos.index") }}"
                            >
                                Pagos
                            </a>
                        @else
                            <a
                                class="module-bar__link {{ request()->routeIs("clientes.index") ? "is-active" : "" }}"
                                href="{{ route("clientes.index") }}"
                            >
                                Organizaciones
                            </a>
                            <a
                                class="module-bar__link {{ request()->routeIs("clientes.create") ? "is-active" : "" }}"
                                href="{{ route("clientes.create") }}"
                            >
                                Nueva organización
                            </a>
                        @endif
                    </nav>
                </div>
            @endif

            <main class="app-main">
                <x-ui.flash />
                @yield("content")
            </main>
        </div>
        @stack("scripts")
    </body>
</html>

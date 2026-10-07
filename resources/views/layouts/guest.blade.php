<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>@yield("title", "Acceso") · INGHERN</title>
        <link rel="icon" href="{{ asset("images/brand/favicon-32.png") }}" />
        @vite(["resources/css/app.css", "resources/js/app.js"])
    </head>
    <body>
        <main class="auth-page">
            <section class="auth-card-area">
                <img
                    class="auth-brand"
                    src="{{ asset("images/brand/inghern-logo.png") }}"
                    alt="INGHERN"
                />
                @yield("content")
            </section>
            <aside class="auth-visual">
                <div class="auth-visual__content">
                    <div class="auth-visual__mark"></div>
                    <h2>Ingeniería que convierte información en decisiones.</h2>
                    <p>
                        Una operación trazable para coordinar servicios,
                        clientes y propuestas técnicas desde un mismo lugar.
                    </p>
                </div>
            </aside>
        </main>
    </body>
</html>

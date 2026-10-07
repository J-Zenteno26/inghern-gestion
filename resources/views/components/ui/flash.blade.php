@if (session("exito"))
    <div class="flash" data-flash>
        {{ session("exito") }}
        <button
            class="flash__close"
            type="button"
            data-dismiss-flash
            aria-label="Cerrar"
        >
            ×
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="flash flash--error" data-flash>
        Revisa los campos marcados antes de continuar.
        <button
            class="flash__close"
            type="button"
            data-dismiss-flash
            aria-label="Cerrar"
        >
            ×
        </button>
    </div>
@endif

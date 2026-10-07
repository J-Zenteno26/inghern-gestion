@extends('layouts.app')
@section('title', 'Registrar factura')
@section('content')
    @php
        $seleccionId = (int) old('orden_compra_id', $ordenCompraSeleccionada?->id ?? 0);
        $ordenCompraActiva = $ordenesCompra->firstWhere('id', $seleccionId);
    @endphp

    <div class="invoice-create-page" data-invoice-form>
        <x-ui.page-header
            eyebrow="Comercial · Facturas"
            title="Registrar factura"
            description="Emite una factura sobre una orden de compra registrada."
        >
            <x-slot:actions>
                <x-ui.button :href="route('facturas.index')" variant="outline">
                    Volver a facturas
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="POST" action="{{ route('facturas.store') }}" class="invoice-create-layout">
            @csrf

            <x-ui.panel class="invoice-create-form">
                <x-slot:title>
                <style>
                    .invoice-create-form,
                    .invoice-context-panel {
                        overflow: hidden;
                        border-color: #c8dbe4;
                        border-radius: var(--radius-lg);
                        box-shadow: 0 14px 32px rgb(15 56 77 / 8%);
                    }

                    .invoice-create-form > .ui-panel__header {
                        border-bottom-color: #24566f;
                        background: linear-gradient(135deg, #16445c, #25627d);
                    }

                    .invoice-create-form > .ui-panel__header .ui-panel__title,
                    .invoice-create-form > .ui-panel__header .ui-panel__subtitle {
                        color: #fff;
                    }

                    .invoice-create-form > .ui-panel__header .ui-panel__subtitle {
                        opacity: .78;
                    }

                    .invoice-context-panel > .ui-panel__header {
                        border-bottom-color: #b7d3df;
                        background: linear-gradient(135deg, #d9ebf2, #edf6f9);
                    }

                    .invoice-context-panel > .ui-panel__header .ui-panel__title {
                        color: #16445c;
                    }

                    .invoice-panel-heading,
                    .invoice-condition-control {
                        display: flex;
                        align-items: center;
                        gap: .65rem;
                    }

                    .invoice-panel-heading > span {
                        display: inline-grid;
                        width: 2rem;
                        height: 2rem;
                        flex: 0 0 auto;
                        place-items: center;
                        border-radius: var(--radius-sm);
                        background: rgb(255 255 255 / 14%);
                        color: #fff;
                    }

                    .invoice-context-panel .invoice-panel-heading > span {
                        background: #fff;
                        color: #25627d;
                    }

                    .invoice-create-form > .ui-panel__body {
                        background: #f3f7f9;
                    }

                    .invoice-create-form .form-grid {
                        padding: 1rem;
                        border: 0;
                        border-radius: 0;
                        background: #fff;
                    }

                    .invoice-context-panel > .ui-panel__body {
                        background: linear-gradient(180deg, #fbfdfe, #f3f8fa);
                    }

                    .invoice-combobox,
                    .invoice-combobox__control,
                    .invoice-input-with-icon,
                    .invoice-money-input {
                        position: relative;
                    }

                    .invoice-combobox__leading,
                    .invoice-input-with-icon > svg,
                    .invoice-money-input > svg {
                        position: absolute;
                        z-index: 1;
                        top: 50%;
                        left: .85rem;
                        transform: translateY(-50%);
                        color: #527182;
                        pointer-events: none;
                    }

                    .invoice-combobox__input,
                    .invoice-input-with-icon input {
                        padding-left: 2.65rem;
                    }

                    .invoice-combobox__input {
                        padding-right: 2.8rem;
                    }

                    .invoice-combobox__toggle {
                        position: absolute;
                        top: 50%;
                        right: .45rem;
                        display: grid;
                        width: 2rem;
                        height: 2rem;
                        transform: translateY(-50%);
                        place-items: center;
                        border-radius: var(--radius-sm);
                        color: #527182;
                    }

                    .invoice-combobox__toggle:hover {
                        background: #eaf2f5;
                        color: #16445c;
                    }

                    .invoice-combobox__list {
                        position: absolute;
                        z-index: 30;
                        top: calc(100% + .4rem);
                        right: 0;
                        left: 0;
                        max-height: 17rem;
                        overflow-y: auto;
                        padding: .35rem;
                        border: 1px solid #bfd3dc;
                        border-radius: var(--radius-md);
                        background: #fff;
                        box-shadow: 0 18px 38px rgb(15 56 77 / 18%);
                    }

                    .invoice-combobox__option {
                        display: flex;
                        width: 100%;
                        align-items: baseline;
                        gap: .4rem;
                        padding: .7rem .75rem;
                        border-radius: var(--radius-sm);
                        color: #334e5d;
                        text-align: left;
                    }

                    .invoice-combobox__option:hover,
                    .invoice-combobox__option:focus,
                    .invoice-combobox__option[aria-selected='true'] {
                        outline: none;
                        background: #e7f1f5;
                        color: #123e55;
                    }

                    .invoice-combobox__empty {
                        padding: .8rem;
                        color: #6f8590;
                        text-align: center;
                    }

                    .invoice-money-input {
                        display: flex;
                        min-height: 2.75rem;
                        align-items: center;
                        padding: 0 .85rem 0 2.65rem;
                        border: 1px solid #bfced5;
                        border-radius: var(--radius-sm);
                        background: #fff;
                    }

                    .invoice-money-input:focus-within {
                        border-color: #28708f;
                        box-shadow: 0 0 0 3px rgb(40 112 143 / 13%);
                    }

                    .invoice-money-input__prefix {
                        flex: 0 0 auto;
                        color: #6c818c;
                        font-size: .88rem;
                        font-weight: 700;
                    }

                    .invoice-money-input__field {
                        min-width: 0;
                        flex: 1;
                        border: 0;
                        outline: 0;
                        background: transparent;
                        color: #153e52;
                        font-family: 'Rubik', sans-serif;
                        font-size: 1rem;
                        font-weight: 700;
                    }

                    .invoice-condition-control > svg {
                        flex: 0 0 auto;
                        color: #527182;
                    }

                    [data-payment-days-field] {
                        max-width: 10rem;
                    }

                    .invoice-observation {
                        overflow: hidden;
                        border: 1px solid #d7e4e9;
                        border-radius: var(--radius-sm);
                        background: #fff;
                    }

                    .invoice-observation > summary {
                        padding: .85rem 1rem;
                    }

                    .invoice-observation > :not(summary) {
                        margin-right: 1rem;
                        margin-bottom: 1rem;
                        margin-left: 1rem;
                    }

                    .invoice-context > div,
                    .invoice-total-preview > div {
                        grid-template-columns: 1.65rem minmax(0, 1fr);
                        column-gap: .6rem;
                        border-radius: var(--radius-sm);
                    }

                    .invoice-context > div > svg,
                    .invoice-total-preview > div > svg {
                        grid-row: 1 / span 2;
                        align-self: center;
                        color: #527182;
                    }

                    .invoice-context > div > span,
                    .invoice-context > div > strong,
                    .invoice-total-preview > div > span,
                    .invoice-total-preview > div > strong,
                    .invoice-total-preview > div > .ui-badge {
                        grid-column: 2;
                    }

                    .invoice-context > div > strong,
                    .invoice-total-preview > div > strong {
                        min-width: 0;
                        white-space: nowrap;
                        font-variant-numeric: tabular-nums;
                    }

                    .invoice-total-preview > div:has([data-invoice-tax]),
                    .invoice-total-preview > div:has([data-invoice-resulting-balance]),
                    .invoice-total-preview > div:has([data-invoice-total]) {
                        grid-column: 1 / -1;
                    }

                    .invoice-total-preview > div:last-child {
                        border-color: #c9dce4;
                        background: #eaf3f7;
                    }
                </style>

                <div class="invoice-panel-heading">
                        <span><x-ui.icon name="receipt" size="19" /></span>
                        <h2 class="ui-panel__title">Datos de factura</h2>
                    </div>
                </x-slot:title>
                <x-slot:subtitle>IVA, total, moneda y estado se heredan automáticamente.</x-slot:subtitle>

                @error('factura')
                    <div class="invoice-form-error">{{ $message }}</div>
                @enderror

                <div class="form-grid">
                    <x-ui.field name="orden_compra_id" label="Orden de compra" required class="form-col-12">
                        <div class="invoice-combobox" data-invoice-combobox>
                            <x-ui.icon name="receipt" size="17" class="invoice-combobox__leading" />
                            <input
                                class="ui-control invoice-combobox__input {{ $errors->has('orden_compra_id') ? 'is-invalid' : '' }}"
                                type="text"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="invoice-order-options"
                                placeholder="Escribe una OC o una organización"
                                autocomplete="off"
                                required
                                value="{{ $ordenCompraActiva ? $ordenCompraActiva->numero.' · '.$ordenCompraActiva->cliente->nombre_display : '' }}"
                                data-invoice-order-combobox
                            />
                            <button
                                class="invoice-combobox__toggle"
                                type="button"
                                aria-label="Mostrar órdenes de compra"
                                data-invoice-order-toggle
                            >
                                <x-ui.icon name="chevron-down" size="17" />
                            </button>
                            <input
                                type="hidden"
                                name="orden_compra_id"
                                value="{{ $seleccionId ?: '' }}"
                                data-invoice-order
                            />
                            <div
                                class="invoice-combobox__list"
                                id="invoice-order-options"
                                role="listbox"
                                data-invoice-order-list
                                hidden
                            >
                                @foreach ($ordenesCompra as $ordenCompra)
                                    @php
                                        $netoFacturadoOc = (float) ($ordenCompra->neto_facturado ?? 0);
                                        $saldoOc = (float) $ordenCompra->monto - $netoFacturadoOc;
                                    @endphp
                                    <button
                                        type="button"
                                        role="option"
                                        class="invoice-combobox__option"
                                        aria-selected="{{ $seleccionId === $ordenCompra->id ? 'true' : 'false' }}"
                                        data-invoice-order-option
                                        data-value="{{ $ordenCompra->id }}"
                                        data-label="{{ $ordenCompra->numero }} · {{ $ordenCompra->cliente->nombre_display }}"
                                        data-organizacion="{{ $ordenCompra->cliente->nombre_display }}"
                                        data-monto="{{ (float) $ordenCompra->monto }}"
                                        data-facturado="{{ $netoFacturadoOc }}"
                                        data-saldo="{{ $saldoOc }}"
                                        data-iva="{{ (float) $ordenCompra->cotizacion->revisionActual->iva_porcentaje }}"
                                        data-moneda="{{ $ordenCompra->cotizacion->revisionActual->moneda }}"
                                    >
                                        <strong>{{ $ordenCompra->numero }}</strong>
                                        <span>· {{ $ordenCompra->cliente->nombre_display }}</span>
                                    </button>
                                @endforeach
                                <div class="invoice-combobox__empty" data-invoice-order-empty hidden>
                                    No se encontraron órdenes de compra.
                                </div>
                            </div>
                        </div>
                    </x-ui.field>

                    <x-ui.field name="folio" label="Folio" required class="form-col-6">
                        <x-ui.input name="folio" required maxlength="100" />
                    </x-ui.field>

                    <x-ui.field name="fecha_emision" label="Fecha de emisión" required class="form-col-6">
                        <div class="invoice-input-with-icon">
                            <x-ui.icon name="calendar-days" size="17" />
                            <x-ui.input name="fecha_emision" type="date" :value="now()->toDateString()" required />
                        </div>
                    </x-ui.field>

                    <x-ui.field name="monto_neto" label="Monto neto" required class="form-col-12">
                        <div class="invoice-money-input {{ $errors->has('monto_neto') ? 'is-invalid' : '' }}">
                            <x-ui.icon name="banknote" size="18" />
                            <span class="invoice-money-input__prefix" data-invoice-net-currency>CLP $</span>
                            <input
                                class="invoice-money-input__field"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                placeholder="0"
                                data-invoice-net-display
                            />
                            <input
                                type="hidden"
                                name="monto_neto"
                                value="{{ old('monto_neto') }}"
                                data-invoice-net
                                data-has-value="{{ old('monto_neto') !== null ? '1' : '0' }}"
                            />
                        </div>
                    </x-ui.field>

                    <x-ui.field name="condicion_pago" label="Condición de pago" required class="form-col-6">
                        <div class="invoice-condition-control">
                            <x-ui.icon name="circle-check" size="17" />
                            <div class="ui-choice-group" data-payment-condition>
                            <label class="ui-choice">
                                <input
                                    type="radio"
                                    name="condicion_pago"
                                    value="credito"
                                    @checked(old('condicion_pago', 'credito') === 'credito')
                                />
                                <span>Crédito</span>
                            </label>
                            <label class="ui-choice">
                                <input
                                    type="radio"
                                    name="condicion_pago"
                                    value="contado"
                                    @checked(old('condicion_pago') === 'contado')
                                />
                                <span>Al contado</span>
                            </label>
                            </div>
                        </div>
                    </x-ui.field>

                    <x-ui.field
                        name="dias_pago"
                        label="Días de pago"
                        class="form-col-6"
                        data-payment-days-field
                    >
                        <x-ui.input
                            name="dias_pago"
                            type="number"
                            :value="45"
                            min="1"
                            max="3650"
                            step="1"
                            data-payment-days
                        />
                    </x-ui.field>
                </div>

                <details class="invoice-observation" @if ($errors->has('observacion') || filled(old('observacion'))) open @endif>
                    <summary><span aria-hidden="true">+</span> Añadir observación</summary>
                    <x-ui.field name="observacion" label="Observación" hint="Opcional">
                        <x-ui.textarea name="observacion" rows="3" maxlength="5000" />
                    </x-ui.field>
                </details>

                <div class="form-actions">
                    <x-ui.button :href="route('facturas.index')" variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit">
                        <x-ui.icon name="receipt" size="16" />
                        Registrar factura
                    </x-ui.button>
                </div>
            </x-ui.panel>

            <aside class="invoice-create-summary">
                <x-ui.panel class="invoice-context-panel">
                    <x-slot:title>
                        <div class="invoice-panel-heading">
                            <span><x-ui.icon name="briefcase-business" size="19" /></span>
                            <h2 class="ui-panel__title">Contexto de la OC</h2>
                        </div>
                    </x-slot:title>
                    <div class="invoice-context" data-invoice-context>
                        <div>
                            <x-ui.icon name="building-2" size="16" />
                            <span>Organización</span>
                            <strong data-invoice-organization>Selecciona una OC</strong>
                        </div>
                        <div>
                            <x-ui.icon name="receipt" size="16" />
                            <span>Monto OC</span>
                            <strong data-invoice-order-amount>—</strong>
                        </div>
                        <div>
                            <x-ui.icon name="banknote" size="16" />
                            <span>Neto ya facturado</span>
                            <strong data-invoice-billed>—</strong>
                        </div>
                        <div>
                            <x-ui.icon name="calculator" size="16" />
                            <span>Saldo por facturar</span>
                            <strong data-invoice-balance>—</strong>
                        </div>
                    </div>
                    <div class="invoice-total-preview">
                        <div>
                            <x-ui.icon name="calculator" size="16" />
                            <span>Tasa IVA</span>
                            <strong data-invoice-tax-rate>—</strong>
                        </div>
                        <div>
                            <x-ui.icon name="banknote" size="16" />
                            <span>IVA calculado</span>
                            <strong data-invoice-tax>—</strong>
                        </div>
                        <div>
                            <x-ui.icon name="calculator" size="16" />
                            <span>Saldo resultante</span>
                            <strong data-invoice-resulting-balance>—</strong>
                        </div>
                        <div class="invoice-total-preview__total">
                            <x-ui.icon name="banknote" size="16" />
                            <span>Total factura</span>
                            <strong data-invoice-total>—</strong>
                        </div>
                        <div>
                            <x-ui.icon name="circle-check" size="16" />
                            <span>Estado inicial</span>
                            <x-ui.badge status="emitida" tone="info" />
                        </div>
                    </div>
                    <div class="invoice-overage-warning" data-invoice-warning hidden>
                        El neto acumulado superará el monto de la OC.
                    </div>
                </x-ui.panel>
            </aside>
        </form>
    </div>
@endsection

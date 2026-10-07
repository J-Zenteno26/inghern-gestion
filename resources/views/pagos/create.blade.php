@extends('layouts.app')
@section('title', 'Registrar pago')
@section('content')
    @php
        $asignaciones = old('asignaciones');
        if (! is_array($asignaciones)) {
            $asignaciones = [[
                'factura_id' => $facturaSeleccionada?->id,
                'monto_asignado' => $facturaSeleccionada ? max($facturaSeleccionada->saldoPago(), 0) : null,
            ]];
        }
        $asignaciones = array_values($asignaciones);
        while (count($asignaciones) < 2) {
            $asignaciones[] = ['factura_id' => null, 'monto_asignado' => null];
        }
        $segundaAsignacionVisible = filled($asignaciones[1]['factura_id'] ?? null)
            || filled($asignaciones[1]['monto_asignado'] ?? null);
        $montoTotalInicial = old('monto_total', $facturaSeleccionada ? max($facturaSeleccionada->saldoPago(), 0) : null);
    @endphp

    <div class="payment-create-page" data-payment-form>
        <x-ui.page-header
            eyebrow="Comercial · Pagos"
            title="Registrar pago"
            description="Registra un ingreso y aplícalo a su factura. Si corresponde, puedes distribuirlo entre dos facturas."
        >
            <x-slot:actions>
                <x-ui.button :href="route('pagos.index')" variant="outline">Volver a pagos</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="POST" action="{{ route('pagos.store') }}" class="payment-create-layout">
            @csrf
            <x-ui.panel class="payment-create-form">
                <x-slot:title>
                    <div class="payment-panel-heading">
                        <span><x-ui.icon name="banknote" size="19" /></span>
                        <h2 class="ui-panel__title">Datos del pago</h2>
                    </div>
                </x-slot:title>
                <x-slot:subtitle>Completa el ingreso y deja que INGHERN calcule su aplicación.</x-slot:subtitle>

                <div class="form-grid payment-form-grid payment-form-grid--compact">
                    <x-ui.field name="fecha_pago" label="Fecha de pago" required class="form-col-6">
                        <div class="payment-input-with-icon">
                            <x-ui.icon name="calendar-days" size="17" />
                            <x-ui.input name="fecha_pago" type="date" :value="old('fecha_pago', now()->toDateString())" required />
                        </div>
                    </x-ui.field>

                    <x-ui.field name="medio_pago" label="Medio de pago" required class="form-col-6">
                        <x-ui.select name="medio_pago" required>
                            <option value="">Selecciona un medio</option>
                            @foreach ($mediosPago as $value => $label)
                                <option value="{{ $value }}" @selected(old('medio_pago') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field name="monto_total" label="Monto recibido" required class="form-col-6">
                        <div class="payment-money-input {{ $errors->has('monto_total') ? 'is-invalid' : '' }}">
                            <x-ui.icon name="banknote" size="18" />
                            <span class="payment-money-input__prefix" data-payment-total-currency>CLP $</span>
                            <input class="payment-money-input__field" type="text" inputmode="numeric" autocomplete="off" placeholder="0" data-payment-total-display />
                            <input type="hidden" name="monto_total" value="{{ $montoTotalInicial }}" data-payment-total />
                        </div>
                    </x-ui.field>

                    <x-ui.field name="referencia" label="Referencia" hint="Opcional" class="form-col-6">
                        <x-ui.input name="referencia" maxlength="120" placeholder="Número de operación, cheque u otra referencia" />
                    </x-ui.field>
                </div>

                <section class="payment-allocations">
                    <div class="payment-allocations__header">
                        <div>
                            <h3>Aplicar pago a factura</h3>
                            <p>Selecciona la factura y define el porcentaje o monto recibido. Ambos valores se sincronizan automáticamente.</p>
                        </div>
                    </div>

                    @error('asignaciones')
                        <div class="payment-form-error">{{ $message }}</div>
                    @enderror

                    @foreach ([0, 1] as $index)
                        @php
                            $facturaId = (int) ($asignaciones[$index]['factura_id'] ?? 0);
                            $facturaActiva = $facturas->firstWhere('id', $facturaId);
                            $visible = $index === 0 || $segundaAsignacionVisible;
                        @endphp

                        <div
                            class="payment-allocation {{ $index === 1 ? 'payment-allocation--secondary' : '' }}"
                            data-payment-allocation
                            data-allocation-index="{{ $index }}"
                            @if (! $visible) hidden @endif
                        >
                            <div class="payment-allocation__heading">
                                <div>
                                    <strong>{{ $index === 0 ? 'Factura vinculada' : 'Segunda factura' }}</strong>
                                    @if ($index === 1)
                                        <small>Usa esta opción solo si el mismo pago cubre más de una factura.</small>
                                    @endif
                                </div>
                                @if ($index === 1)
                                    <button type="button" class="payment-allocation__remove" data-payment-remove-allocation>Quitar</button>
                                @endif
                            </div>

                            <x-ui.field :name="'asignaciones.'.$index.'.factura_id'" label="Factura" required>
                                <div class="payment-combobox" data-payment-combobox>
                                    <x-ui.icon name="receipt" size="17" class="payment-combobox__leading" />
                                    <input
                                        class="ui-control payment-combobox__input"
                                        type="text"
                                        role="combobox"
                                        aria-autocomplete="list"
                                        aria-expanded="false"
                                        aria-controls="payment-invoice-options-{{ $index }}"
                                        placeholder="Escribe un folio u organización"
                                        autocomplete="off"
                                        value="{{ $facturaActiva ? $facturaActiva->folio.' · '.$facturaActiva->cliente->nombre_display.' · Saldo '.$facturaActiva->moneda.' $'.number_format($facturaActiva->saldoPago(), 0, ',', '.') : '' }}"
                                        data-payment-invoice-search
                                        @disabled(! $visible)
                                        required
                                    />
                                    <button class="payment-combobox__toggle" type="button" aria-label="Mostrar facturas" data-payment-invoice-toggle @disabled(! $visible)>
                                        <x-ui.icon name="chevron-down" size="17" />
                                    </button>
                                    <input type="hidden" name="asignaciones[{{ $index }}][factura_id]" value="{{ $facturaId ?: '' }}" data-payment-invoice-id @disabled(! $visible) />

                                    <div class="payment-combobox__list" id="payment-invoice-options-{{ $index }}" role="listbox" data-payment-invoice-list hidden>
                                        @foreach ($facturas as $factura)
                                            @php
                                                $saldoFactura = $factura->saldoPago();
                                                $optionLabel = $factura->folio.' · '.$factura->cliente->nombre_display.' · Saldo '.$factura->moneda.' $'.number_format($saldoFactura, 0, ',', '.');
                                            @endphp
                                            <button
                                                type="button"
                                                role="option"
                                                class="payment-combobox__option"
                                                aria-selected="{{ $facturaId === $factura->id ? 'true' : 'false' }}"
                                                data-payment-invoice-option
                                                data-value="{{ $factura->id }}"
                                                data-label="{{ $optionLabel }}"
                                                data-folio="{{ $factura->folio }}"
                                                data-organization="{{ $factura->cliente->nombre_display }}"
                                                data-total="{{ (float) $factura->total }}"
                                                data-paid="{{ $factura->totalPagado() }}"
                                                data-balance="{{ $saldoFactura }}"
                                                data-currency="{{ $factura->moneda }}"
                                            >
                                                <strong>{{ $factura->folio }}</strong>
                                                <span>{{ $factura->cliente->nombre_display }}</span>
                                                <small>Saldo {{ $factura->moneda }} &#36;{{ number_format($saldoFactura, 0, ',', '.') }}</small>
                                            </button>
                                        @endforeach
                                        <div class="payment-combobox__empty" data-payment-invoice-empty hidden>No se encontraron facturas.</div>
                                    </div>
                                </div>
                            </x-ui.field>

                            <div class="payment-allocation__financial-context">
                                <div>
                                    <span>Total factura</span>
                                    <strong data-allocation-total>—</strong>
                                </div>
                                <div>
                                    <span>Ya pagado</span>
                                    <strong data-allocation-paid>—</strong>
                                </div>
                                <div class="payment-allocation__financial-context--primary">
                                    <span>Saldo pendiente</span>
                                    <strong data-allocation-balance>—</strong>
                                </div>
                            </div>

                            <div class="payment-allocation__application">
                                <div class="payment-allocation__application-heading">
                                    <span>Este pago</span>
                                    <small>100% corresponde al saldo pendiente actual, no al total histórico de la factura.</small>
                                </div>

                                <div class="payment-allocation__controls">
                                    <x-ui.field :name="'asignaciones.'.$index.'.porcentaje'" label="Porcentaje del saldo">
                                        <div class="payment-percent-input">
                                            <input
                                                class="payment-percent-input__field"
                                                type="number"
                                                inputmode="decimal"
                                                min="0"
                                                step="0.01"
                                                placeholder="100"
                                                data-payment-allocation-percent
                                                @disabled(! $visible)
                                            />
                                            <span>%</span>
                                        </div>
                                        <small class="payment-allocation__equivalence" data-allocation-original-percent>—</small>
                                    </x-ui.field>

                                    <x-ui.field :name="'asignaciones.'.$index.'.monto_asignado'" label="Monto asignado" required>
                                        <div class="payment-money-input">
                                            <x-ui.icon name="banknote" size="18" />
                                            <span class="payment-money-input__prefix" data-allocation-currency>CLP $</span>
                                            <input class="payment-money-input__field" type="text" inputmode="numeric" autocomplete="off" placeholder="0" data-payment-allocation-display @disabled(! $visible) />
                                            <input type="hidden" name="asignaciones[{{ $index }}][monto_asignado]" value="{{ $asignaciones[$index]['monto_asignado'] ?? '' }}" data-payment-allocation-amount @disabled(! $visible) />
                                        </div>
                                    </x-ui.field>
                                </div>
                            </div>

                            <div class="payment-allocation__result">
                                <div>
                                    <span>Saldo después del pago</span>
                                    <strong data-allocation-result>—</strong>
                                </div>
                                <div>
                                    <span>Pendiente del saldo</span>
                                    <strong data-allocation-pending-percent>—</strong>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="payment-allocation-add">
                        <x-ui.button
                            type="button"
                            variant="secondary"
                            size="small"
                            data-payment-add-allocation
                            :hidden="$segundaAsignacionVisible"
                        >
                            <x-ui.icon name="plus" size="15" /> Distribuir también en otra factura
                        </x-ui.button>
                        <small>Úsalo solo cuando el mismo ingreso cubra dos facturas.</small>
                    </div>
                </section>

                <details class="payment-observation" @if ($errors->has('observacion') || filled(old('observacion'))) open @endif>
                    <summary><span aria-hidden="true">+</span> Añadir observación</summary>
                    <x-ui.field name="observacion" label="Observación" hint="Opcional">
                        <x-ui.textarea name="observacion" rows="3" maxlength="5000" />
                    </x-ui.field>
                </details>

                <div class="form-actions">
                    <x-ui.button :href="route('pagos.index')" variant="ghost">Cancelar</x-ui.button>
                    <x-ui.button type="submit"><x-ui.icon name="banknote" size="16" /> Registrar pago</x-ui.button>
                </div>
            </x-ui.panel>

            <aside class="payment-create-summary">
                <x-ui.panel class="payment-summary-panel">
                    <x-slot:title>
                        <div class="payment-panel-heading">
                            <span><x-ui.icon name="calculator" size="19" /></span>
                            <h2 class="ui-panel__title">Resumen del pago</h2>
                        </div>
                    </x-slot:title>
                    <x-slot:subtitle>Distribución y saldo del ingreso en tiempo real.</x-slot:subtitle>

                    <div class="payment-summary-values">
                        <div class="payment-summary-value payment-summary-value--received">
                            <span class="payment-summary-value__icon"><x-ui.icon name="banknote" size="18" /></span>
                            <div>
                                <span>Monto recibido</span>
                                <strong data-payment-summary-total>CLP $0</strong>
                            </div>
                        </div>
                        <div class="payment-summary-value payment-summary-value--distributed">
                            <span class="payment-summary-value__icon"><x-ui.icon name="circle-check" size="18" /></span>
                            <div>
                                <span>Monto distribuido</span>
                                <strong data-payment-summary-distributed>CLP $0</strong>
                            </div>
                        </div>
                        <div class="payment-summary-value payment-summary-value--remaining">
                            <span class="payment-summary-value__icon"><x-ui.icon name="calculator" size="18" /></span>
                            <div>
                                <span>Restante por asignar</span>
                                <strong data-payment-summary-remaining>CLP $0</strong>
                            </div>
                        </div>
                    </div>

                    <div class="payment-summary-divider">
                        <span>Aplicación del pago</span>
                    </div>

                    <div class="payment-summary-invoices" data-payment-summary-invoices>
                        <p>Selecciona al menos una factura.</p>
                    </div>

                    <div class="payment-overpayment-warning" data-payment-overpayment-warning hidden>
                        <x-ui.icon name="circle-alert" size="17" />
                        La asignación dejará una o más facturas sobrepagadas.
                    </div>
                </x-ui.panel>
            </aside>
        </form>
    </div>
@endsection

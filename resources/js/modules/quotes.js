const money = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 });

export function initQuotes() {
    const clientSelect = document.querySelector('[data-client-select]');
const quoteForm = document.querySelector('[data-quote-form]');
if (quoteForm) {
    const plantSelect = quoteForm.querySelector('[data-quote-plant-select]');
    const servicesContainer = quoteForm.querySelector('[data-quote-services]');
    const serviceTemplate = document.querySelector('[data-quote-service-template]');
    const lineTemplate = document.querySelector('[data-line-template]');
    const generalCostsContainer = quoteForm.querySelector('[data-general-costs]');
    const generalCostTemplate = document.querySelector('[data-general-cost-template]');
    const catalog = quoteForm.querySelector('[data-service-catalog]');
    const catalogSearch = quoteForm.querySelector('[data-catalog-search]');
    const catalogTypeFilter = quoteForm.querySelector('[data-catalog-type-filter]');
    const pricing = JSON.parse(
        document.querySelector('[data-quote-pricing]')?.textContent || '{}',
    );
    const operationalServices = JSON.parse(
        document.querySelector('[data-operational-services]')?.textContent || '{}',
    );
    const renderedServiceIndexes = [...servicesContainer.querySelectorAll('[data-operational-service]')]
        .map((field) => Number(field.name.match(/^servicios\[(\d+)]/)?.[1] ?? -1));
    let serviceIndex = Math.max(-1, ...renderedServiceIndexes) + 1;
    const renderedCostIndexes = [...generalCostsContainer.querySelectorAll('[data-general-cost-value]')]
        .map((field) => Number(field.name.match(/^costos_generales\[(\d+)]/)?.[1] ?? -1));
    let generalCostIndex = Math.max(-1, ...renderedCostIndexes) + 1;

    function clientId() {
        return quoteForm.querySelector('[name="cliente_id"]')?.value || clientSelect?.value || '';
    }

    function updatePlantOptions() {
        if (!plantSelect) return;

        const selectedClient = clientId();
        let selectedVisible = !plantSelect.value;

        [...plantSelect.options].forEach((option, index) => {
            const visible = index === 0 || option.dataset.client === selectedClient;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && option.selected) selectedVisible = true;
        });

        if (!selectedVisible) plantSelect.value = '';
    }

    function catalogId(serviceBlock) {
        return serviceBlock.querySelector('[data-quote-catalog-service]')?.value || '';
    }

    function selectedCriteria(line) {
        try {
            return JSON.parse(
                line.querySelector('[data-selected-criteria]')?.textContent || '{}',
            );
        } catch {
            return {};
        }
    }

    const linePresets = {
        servicio: {
            label: 'Línea de servicio',
            description: '',
            className: 'servicio',
            justification: 'Precio definido manualmente para una línea adicional del servicio.',
        },
    };

    const generalCostPresets = {
        hospedaje: {
            description: 'Hospedaje',
            className: 'traslado',
            justification: 'Costo de hospedaje asociado a la propuesta.',
        },
        transporte: {
            description: 'Transporte',
            className: 'traslado',
            justification: 'Costo de transporte asociado a la propuesta.',
        },
        alimentacion: {
            description: 'Alimentación',
            className: 'costo_adicional',
            justification: 'Costo de alimentación asociado a la propuesta.',
        },
        movilizacion: {
            description: 'Movilización',
            className: 'traslado',
            justification: 'Costo de movilización asociado a la propuesta.',
        },
        otro: {
            description: '',
            className: 'costo_adicional',
            justification: 'Costo global definido manualmente para la propuesta.',
        },
    };

    function setLineSource(line, text) {
        const referenceDisplay = line.querySelector('[data-reference-display]');
        if (referenceDisplay) referenceDisplay.textContent = text;
    }

    function setLineKind(line, kind, label) {
        line.dataset.lineKind = kind;
        const labelNode = line.querySelector('[data-line-kind-label]');
        if (labelNode) labelNode.textContent = label;
    }

    function setAutomaticJustification(line, text) {
        const justification = line.querySelector('[data-price-justification]');
        if (!justification) return;
        if (!justification.value || justification.dataset.autoJustification === 'true') {
            justification.value = text;
            justification.dataset.autoJustification = 'true';
        }
    }

    function applyLinePreset(line, presetName) {
        const preset = linePresets[presetName] || linePresets.servicio;
        const description = line.querySelector('[data-line-description]');
        const lineClass = line.querySelector('[data-line-class]');
        const method = line.querySelector('[data-price-method]');
        const suggested = line.querySelector('[data-suggested]');
        const price = line.querySelector('[data-price]');
        const referenceHint = line.querySelector('[data-reference-hint]');

        setLineKind(line, presetName, preset.label);
        if (description) {
            description.value = preset.description;
            description.dataset.catalogAutofill = 'false';
        }
        if (lineClass) lineClass.value = preset.className;
        if (method) method.value = 'a_criterio';
        if (suggested) suggested.value = price?.value || '';
        if (referenceHint) referenceHint.textContent = 'Precio definido directamente para esta cotización.';
        setLineSource(line, 'Precio definido manualmente');
        setAutomaticJustification(line, preset.justification);
        line.dataset.priceEdited = 'false';

        if (presetName === 'otro' || presetName === 'servicio') {
            description?.focus();
        } else {
            price?.focus();
        }
    }

    function syncManualLinePricing(line) {
        const price = line.querySelector('[data-price]');
        const suggested = line.querySelector('[data-suggested]');
        const method = line.querySelector('[data-price-method]');
        if (!price || !suggested || !method) return;

        const isPrimary = line.hasAttribute('data-primary-line');
        const serviceBlock = line.closest('[data-quote-service]');
        const config = pricing[catalogId(serviceBlock)];
        const value = Number(price.value || 0);

        if (!isPrimary) {
            suggested.value = value ? value.toFixed(2) : '0.00';
            method.value = 'a_criterio';
            setLineSource(line, 'Precio definido manualmente');
            return;
        }

        if (config?.referencia_disponible) {
            const reference = Number(config.precio_base || 0);
            suggested.value = reference.toFixed(2);
            const matchesReference = Math.abs(value - reference) < 0.005;
            method.value = matchesReference ? 'referencia' : 'a_criterio';
            if (!matchesReference) {
                setAutomaticJustification(line, 'Ajuste comercial sobre la referencia económica del catálogo.');
            }
        } else {
            suggested.value = value ? value.toFixed(2) : '0.00';
            method.value = 'a_criterio';
            setAutomaticJustification(line, 'Precio definido manualmente para este servicio.');
        }
    }

    function buildCriteria(line, config, preserveInitial) {
        const container = line.querySelector('[data-normalized-criteria]');
        const methodName = line.querySelector('[data-price-method]').name;
        const prefix = methodName.replace(/\[metodo_precio]$/, '');
        const selected = preserveInitial ? selectedCriteria(line) : {};

        container.replaceChildren();
        config.variables.forEach((variable) => {
            const field = document.createElement('div');
            field.className = 'ui-field form-col-6';

            const label = document.createElement('label');
            label.className = 'ui-field__label';
            label.textContent = variable.nombre;

            const description = document.createElement('span');
            description.className = 'table-secondary';
            description.textContent = variable.descripcion || 'Sin descripción registrada';

            const select = document.createElement('select');
            select.className = 'ui-control';
            select.name = `${prefix}[criterios][${variable.id}]`;
            select.dataset.normalizedCriterion = '';
            select.dataset.weight = variable.peso;
            select.required = true;

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecciona un nivel';
            select.append(placeholder);

            variable.niveles.forEach((level) => {
                const option = document.createElement('option');
                option.value = level.id;
                option.dataset.factor = level.factor;
                option.textContent = `${level.nombre} · factor ${level.factor}`;
                option.title = level.criterio || '';
                option.selected = String(selected[variable.id] || '') === String(level.id);
                select.append(option);
            });

            field.append(label, description, select);
            container.append(field);
        });
    }

    function recalculateNormalized(line, config) {
        const selects = [...line.querySelectorAll('[data-normalized-criterion]')];
        const complete = selects.length === config.variables.length
            && selects.every((select) => select.value);
        const suggested = line.querySelector('[data-suggested]');

        if (!complete) {
            line.querySelector('[data-normalized-factor]').textContent = 'Completa los criterios';
            line.querySelector('[data-normalized-range]').textContent = 'Sin registro';
            suggested.value = '';
            const referenceDisplay = line.querySelector('[data-reference-display]');
            if (referenceDisplay) referenceDisplay.textContent = 'Completa los criterios para calcular la referencia';
            calculate();
            return;
        }

        const factor = selects.reduce((total, select) => {
            const selected = select.selectedOptions[0];
            const levelFactor = Number(selected.dataset.factor || 1);
            const weight = Number(select.dataset.weight || 1);
            return total * (1 + (levelFactor - 1) * weight);
        }, 1);
        const amount = Math.round(config.precio_base * factor * 100) / 100;
        const minimum = Math.round(amount * 0.9 * 100) / 100;
        const maximum = Math.round(amount * 1.1 * 100) / 100;

        line.querySelector('[data-normalized-factor]').textContent = factor.toFixed(4);
        line.querySelector('[data-normalized-range]').textContent =
            `${config.moneda} ${money.format(minimum)} – ${money.format(maximum)}`;
        suggested.value = amount.toFixed(2);
        const referenceDisplay = line.querySelector('[data-reference-display]');
        if (referenceDisplay) {
            referenceDisplay.textContent = `${config.moneda} ${money.format(amount)} · referencia normalizada`;
        }

        if (line.dataset.priceEdited !== 'true') {
            line.querySelector('[data-price]').value = amount.toFixed(2);
        }
        calculate();
    }

    function configureLine(line, serviceChanged = false) {
        const serviceBlock = line.closest('[data-quote-service]');
        const selectedCatalogId = catalogId(serviceBlock);
        const config = pricing[selectedCatalogId];
        const method = line.querySelector('[data-price-method]');
        const normalizedOption = method.querySelector('[data-normalized-option]');
        const available = Boolean(config?.normalizacion_disponible);
        normalizedOption.disabled = !available;
        if (method.value === 'normalizado' && !available) method.value = 'a_criterio';

        const normalized = method.value === 'normalizado' && available;
        const manualUnit = line.querySelector('[data-manual-unit]');
        const normalizedUnit = line.querySelector('[data-normalized-unit]');
        const panel = line.querySelector('[data-normalized-panel]');
        const suggested = line.querySelector('[data-suggested]');

        manualUnit.hidden = normalized;
        manualUnit.disabled = normalized;
        normalizedUnit.hidden = !normalized;
        normalizedUnit.value = normalized ? config.unidad_etiqueta : '';
        suggested.readOnly = normalized || suggested.dataset.catalogAutofill === 'true';
        panel.hidden = !normalized;

        if (!normalized) {
            panel.querySelectorAll('select').forEach((select) => { select.disabled = true; });
            return;
        }

        const previousCatalog = line.dataset.pricingCatalog;
        if (serviceChanged || previousCatalog !== String(selectedCatalogId)) {
            buildCriteria(line, config, !previousCatalog && !serviceChanged);
            line.dataset.pricingCatalog = selectedCatalogId;
        }
        panel.querySelectorAll('select').forEach((select) => { select.disabled = false; });
        line.querySelector('[data-normalized-base]').textContent =
            `${config.moneda} ${money.format(config.precio_base)}`;
        line.querySelector('[data-normalized-reference-unit]').textContent = config.unidad_etiqueta;
        recalculateNormalized(line, config);
    }

    function configureService(serviceBlock, serviceChanged = false) {
        const catalogSelect = serviceBlock.querySelector('[data-quote-catalog-service]');
        const config = pricing[catalogSelect.value];
        const existing = operationalServices[clientId()]?.[catalogSelect.value] || null;
        const operationalInput = serviceBlock.querySelector('[data-operational-service]');
        const status = serviceBlock.querySelector('[data-quote-service-status]');

        if (config) {
            serviceBlock.querySelector('[data-summary-code]').textContent = config.codigo || 'Servicio del catálogo';
            serviceBlock.querySelector('[data-summary-name]').textContent = config.nombre;
            serviceBlock.querySelector('[data-summary-description]').textContent =
                existing?.descripcion?.trim()
                || config.descripcion?.trim()
                || 'Sin descripción registrada';
        } else {
            serviceBlock.querySelector('[data-summary-code]').textContent = 'Sin servicio seleccionado';
            serviceBlock.querySelector('[data-summary-name]').textContent = 'Selecciona un servicio para comenzar';
            serviceBlock.querySelector('[data-summary-description]').textContent =
                'La descripción configurada en el catálogo aparecerá aquí como contexto.';
        }

        const primaryLine = serviceBlock.querySelector('[data-line]');
        if (primaryLine && serviceChanged) {
            const description = primaryLine.querySelector('[data-line-description]');
            const method = primaryLine.querySelector('[data-price-method]');
            const suggested = primaryLine.querySelector('[data-suggested]');
            const price = primaryLine.querySelector('[data-price]');
            const unit = primaryLine.querySelector('[data-manual-unit]');
            const justification = primaryLine.querySelector('[data-price-justification]');
            const referenceHint = primaryLine.querySelector('[data-reference-hint]');
            const referenceDisplay = primaryLine.querySelector('[data-reference-display]');

            if (config) {
                if (unit && config.unidad) unit.value = config.unidad;
                if (!description.value || description.dataset.catalogAutofill === 'true') {
                    description.value = config.nombre || config.descripcion;
                    description.dataset.catalogAutofill = 'true';
                }

                if (config.referencia_disponible) {
                    suggested.value = Number(config.precio_base).toFixed(2);
                    suggested.dataset.catalogAutofill = 'true';
                    referenceHint.textContent = config.unidad_etiqueta
                        ? `Unidad de referencia: ${config.unidad_etiqueta}`
                        : 'Referencia configurada en el catálogo de servicios.';
                    if (referenceDisplay) {
                        referenceDisplay.textContent = `${config.moneda} ${money.format(config.precio_base)}`;
                    }
                    const useReference = primaryLine.querySelector('[data-use-reference]');
                    if (useReference) useReference.hidden = false;

                    if (primaryLine.dataset.priceEdited !== 'true') {
                        price.value = Number(config.precio_base).toFixed(2);
                    }
                    if (!justification.value || justification.dataset.catalogAutofill === 'true') {
                        justification.value = 'Referencia económica configurada en el catálogo de servicios.';
                        justification.dataset.catalogAutofill = 'true';
                        justification.dataset.autoJustification = 'true';
                    }
                    if (!method.dataset.userSelected || method.value === 'a_criterio') {
                        method.value = 'referencia';
                    }
                } else {
                    if (suggested.dataset.catalogAutofill === 'true') suggested.value = '';
                    suggested.dataset.catalogAutofill = 'false';
                    referenceHint.textContent = 'Define el precio cotizado y deja registrado el criterio utilizado.';
                    if (referenceDisplay) referenceDisplay.textContent = 'Sin referencia configurada';
                    const useReference = primaryLine.querySelector('[data-use-reference]');
                    if (useReference) useReference.hidden = true;
                    setAutomaticJustification(
                        primaryLine,
                        'Precio definido manualmente para este servicio sin referencia económica configurada.',
                    );
                    justification.dataset.catalogAutofill = 'false';
                    if (method.value === 'referencia') method.value = 'a_criterio';
                }
            } else {
                if (description.dataset.catalogAutofill === 'true') description.value = '';
                if (suggested.dataset.catalogAutofill === 'true') suggested.value = '';
                referenceHint.textContent = 'Se completa desde el catálogo del servicio.';
                if (referenceDisplay) referenceDisplay.textContent = 'Sin referencia configurada';
                const useReference = primaryLine.querySelector('[data-use-reference]');
                if (useReference) useReference.hidden = true;
                setAutomaticJustification(
                    primaryLine,
                    'Precio definido manualmente para este servicio sin referencia económica configurada.',
                );
                justification.dataset.catalogAutofill = 'false';
                if (method.value === 'referencia') method.value = 'a_criterio';
            }
        }

        if (existing) {
            operationalInput.value = existing.id;
            serviceBlock.querySelector('[data-summary-operational]').textContent =
                `Servicio existente · ${existing.codigo}`;
            status.textContent = `Servicio existente · ${existing.codigo}`;
        } else {
            if (operationalInput.dataset.preserve !== 'true' || catalogSelect.value) {
                operationalInput.value = '';
            }
            serviceBlock.querySelector('[data-summary-operational]').textContent = catalogSelect.value
                ? 'Nuevo servicio'
                : 'Sin definir';
            status.textContent = catalogSelect.value
                ? 'Nuevo servicio'
                : 'Selecciona un servicio del catálogo';
        }

        serviceBlock.querySelectorAll('[data-line]').forEach((line) => configureLine(line, serviceChanged));
    }

    function serviceBlockForCatalog(catalogServiceId) {
        return [...servicesContainer.querySelectorAll('[data-quote-service]')]
            .find((serviceBlock) => catalogId(serviceBlock) === String(catalogServiceId));
    }

    function updateServiceSelectionUi() {
        const serviceBlocks = [...servicesContainer.querySelectorAll('[data-quote-service]')];
        const selectedCatalogIds = new Set(serviceBlocks.map(catalogId).filter(Boolean));

        quoteForm.querySelectorAll('[data-catalog-checkbox]').forEach((checkbox) => {
            checkbox.checked = selectedCatalogIds.has(checkbox.value);
        });

        serviceBlocks.forEach((serviceBlock, index) => {
            const order = serviceBlock.querySelector('[data-service-order]');
            if (order) order.textContent = String(index + 1).padStart(2, '0');
        });

        const selectedCount = selectedCatalogIds.size;
        const count = quoteForm.querySelector('[data-selected-services-count]');
        const empty = quoteForm.querySelector('[data-included-services-empty]');
        const summary = quoteForm.querySelector('[data-included-services-summary]');
        if (count) count.textContent = selectedCount;
        if (empty) empty.hidden = serviceBlocks.length > 0;
        if (summary) {
            summary.textContent = selectedCount === 1
                ? '1 servicio seleccionado'
                : `${selectedCount} servicios seleccionados`;
        }
    }

    function updateCatalogLinks() {
        quoteForm.querySelectorAll('[data-catalog-card]').forEach((card) => {
            const checkbox = card.querySelector('[data-catalog-checkbox]');
            const status = card.querySelector('[data-catalog-link-status]');
            const existing = operationalServices[clientId()]?.[checkbox.value] || null;
            card.classList.toggle('has-operational-service', Boolean(existing));
            status.textContent = existing
                ? `Servicio existente · ${existing.codigo}`
                : 'Nuevo servicio';
        });
    }

    function filterCatalog() {
        const search = (catalogSearch?.value || '').trim().toLocaleLowerCase('es');
        const selectedType = catalogTypeFilter?.value || '';
        let visibleCards = 0;

        quoteForm.querySelectorAll('[data-catalog-group]').forEach((group) => {
            let visibleInGroup = 0;
            group.querySelectorAll('[data-catalog-card]').forEach((card) => {
                const matchesType = !selectedType || card.dataset.typeId === selectedType;
                const matchesSearch = !search || card.dataset.search.includes(search);
                card.hidden = !(matchesType && matchesSearch);
                if (!card.hidden) visibleInGroup++;
            });
            group.hidden = visibleInGroup === 0;
            visibleCards += visibleInGroup;
        });

        const empty = quoteForm.querySelector('[data-catalog-empty]');
        if (empty) empty.hidden = visibleCards > 0;
    }

    function addCatalogService(catalogServiceId) {
        if (serviceBlockForCatalog(catalogServiceId)) {
            updateServiceSelectionUi();
            return;
        }

        const config = pricing[catalogServiceId];
        if (!config) return;

        servicesContainer.querySelectorAll('[data-quote-service]').forEach((serviceBlock) => {
            setServiceExpanded(serviceBlock, false);
        });

        const html = serviceTemplate.innerHTML
            .replaceAll('__SERVICE_INDEX__', serviceIndex)
            .replaceAll('__CATALOG_ID__', catalogServiceId)
            .replaceAll('__TYPE_ID__', config.tipo_id);
        servicesContainer.insertAdjacentHTML('beforeend', html);
        const serviceBlock = servicesContainer.querySelector('[data-quote-service]:last-child');
        serviceIndex++;
        serviceBlock.querySelectorAll('[data-line]').forEach((line) => {
            line.dataset.priceEdited = 'false';
        });
        configureService(serviceBlock, true);
        updateServiceSelectionUi();
        calculate();
        focusNewService(serviceBlock);
    }

    function removeServiceBlock(serviceBlock) {
        if (!serviceBlock) return;
        serviceBlock.remove();
        updateServiceSelectionUi();
        calculate();
    }

    function setServiceExpanded(serviceBlock, expanded, { focus = false } = {}) {
        const body = serviceBlock?.querySelector('[data-quote-service-body]');
        const toggle = serviceBlock?.querySelector('[data-toggle-quote-service]');
        if (!body || !toggle) return;

        body.hidden = !expanded;
        serviceBlock.classList.toggle('is-collapsed', !expanded);
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('title', expanded ? 'Minimizar servicio' : 'Expandir servicio');

        if (expanded && focus) {
            toggle.focus({ preventScroll: true });
        }
    }

    function focusNewService(serviceBlock) {
        if (!serviceBlock) return;
        setServiceExpanded(serviceBlock, true);
        serviceBlock.classList.add('is-newly-added');
        const bounds = serviceBlock.getBoundingClientRect();
        if (bounds.top < 0 || bounds.bottom > window.innerHeight) {
            serviceBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        window.setTimeout(() => serviceBlock.querySelector('[data-toggle-quote-service]')?.focus({ preventScroll: true }), 450);
        window.setTimeout(() => serviceBlock.classList.remove('is-newly-added'), 1400);
    }

    function selectedText(selector, fallback) {
        const select = quoteForm.querySelector(selector);
        const text = select?.selectedOptions?.[0]?.textContent?.trim();
        return select?.value && text ? text : fallback;
    }

    function renderPreviewItems(selector, rows) {
        const container = quoteForm.querySelector(selector);
        if (!container) return;

        container.replaceChildren();
        rows.forEach(({ label, amount }) => {
            const row = document.createElement('div');
            row.className = 'quote-preview__item';

            const name = document.createElement('span');
            name.textContent = label;
            const value = document.createElement('strong');
            value.textContent = `$${money.format(amount)}`;

            row.append(name, value);
            container.append(row);
        });
    }

    function updatePreviewIdentity() {
        const title = quoteForm.querySelector('[name="titulo"]')?.value.trim();
        quoteForm.querySelector('[data-preview-title]').textContent = title || 'Propuesta sin título';
        quoteForm.querySelector('[data-preview-organization]').textContent = selectedText(
            '[data-client-select]',
            'Organización sin seleccionar',
        );
        quoteForm.querySelector('[data-preview-contact]').textContent = selectedText(
            '[data-contact-select]',
            'Sin contacto definido',
        );
    }

    function updateGeneralCostsEmptyState() {
        const empty = quoteForm.querySelector('[data-general-costs-empty]');
        if (empty) empty.hidden = generalCostsContainer.querySelectorAll('[data-general-cost]').length > 0;
    }

    function calculate() {
        let subtotal = 0;
        const serviceRows = [];

        quoteForm.querySelectorAll('[data-quote-service]').forEach((serviceBlock) => {
            let serviceSubtotal = 0;

            serviceBlock.querySelectorAll('[data-line]').forEach((line) => {
                const qty = Number(line.querySelector('[data-qty]')?.value || 0);
                const price = Number(line.querySelector('[data-price]')?.value || 0);
                const amount = qty * price;
                serviceSubtotal += amount;

                const lineTotal = line.querySelector('[data-line-total]');
                if (lineTotal) lineTotal.textContent = `$${money.format(amount)}`;
            });

            const serviceTotal = serviceBlock.querySelector('[data-service-subtotal]');
            if (serviceTotal) serviceTotal.textContent = `$${money.format(serviceSubtotal)}`;
            subtotal += serviceSubtotal;
            serviceRows.push({
                label: serviceBlock.querySelector('[data-summary-name]')?.textContent.trim() || 'Servicio',
                amount: serviceSubtotal,
            });
        });

        let generalCostsSubtotal = 0;
        const generalCostRows = [];
        generalCostsContainer.querySelectorAll('[data-general-cost]').forEach((cost) => {
            const value = Number(cost.querySelector('[data-general-cost-value]')?.value || 0);
            const suggested = cost.querySelector('[data-general-cost-suggested]');
            if (suggested) suggested.value = value.toFixed(2);
            generalCostsSubtotal += value;
            generalCostRows.push({
                label: cost.querySelector('[data-general-cost-description]')?.value.trim() || 'Costo sin descripción',
                amount: value,
            });
        });
        subtotal += generalCostsSubtotal;

        const taxRate = Number(quoteForm.querySelector('[data-iva]')?.value || 0);
        const tax = subtotal * taxRate / 100;
        quoteForm.querySelector('[data-general-costs-subtotal]').textContent = `$${money.format(generalCostsSubtotal)}`;
        quoteForm.querySelector('[data-subtotal]').textContent = `$${money.format(subtotal)}`;
        quoteForm.querySelector('[data-tax]').textContent = `$${money.format(tax)}`;
        quoteForm.querySelector('[data-total]').textContent = `$${money.format(subtotal + tax)}`;
        renderPreviewItems('[data-preview-services]', serviceRows);
        renderPreviewItems('[data-preview-costs]', generalCostRows);
        quoteForm.querySelector('[data-preview-services-empty]').hidden = serviceRows.length > 0;
        quoteForm.querySelector('[data-preview-costs-empty]').hidden = generalCostRows.length > 0;
        updatePreviewIdentity();
        updateGeneralCostsEmptyState();
    }

    function fieldValidationContainer(field) {
        if (field.matches('[data-catalog-checkbox]')) {
            return quoteForm.querySelector('.quote-included-services');
        }

        return field.closest('.ui-field');
    }

    function clearFieldValidation(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        fieldValidationContainer(field)?.querySelector('[data-client-validation-error]')?.remove();
    }

    function fieldValidationMessage(field) {
        if (field.validity.customError) return field.validationMessage;
        if (field.validity.valueMissing) return 'Completa este campo para guardar el borrador.';
        if (field.validity.rangeUnderflow) return `El valor mínimo permitido es ${field.min}.`;
        if (field.validity.rangeOverflow) return `El valor máximo permitido es ${field.max}.`;
        if (field.validity.badInput || field.validity.typeMismatch) return 'Ingresa un valor válido.';
        return field.validationMessage || 'Revisa el valor ingresado.';
    }

    function showFieldValidation(field) {
        clearFieldValidation(field);
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');

        const fieldContainer = fieldValidationContainer(field);
        if (!fieldContainer) return;

        const error = document.createElement('span');
        error.className = 'ui-field__error';
        error.dataset.clientValidationError = '';
        error.textContent = fieldValidationMessage(field);
        fieldContainer.append(error);
    }

    function showValidationSummary(invalidFields) {
        const summary = quoteForm.querySelector('[data-quote-validation-summary]');
        const message = quoteForm.querySelector('[data-quote-validation-message]');
        if (!summary || !message) return;

        const count = invalidFields.length;
        message.textContent = count === 1
            ? 'Falta completar un campo requerido.'
            : `Faltan completar ${count} campos requeridos.`;
        summary.hidden = false;
        summary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function focusFirstInvalidField(field) {
        if (field.matches('[data-catalog-checkbox]') && catalog.hidden) {
            catalog.hidden = false;
            const toggleCatalog = quoteForm.querySelector('[data-toggle-service-catalog]');
            toggleCatalog?.setAttribute('aria-expanded', 'true');
            const label = toggleCatalog?.querySelector('[data-catalog-toggle-label]');
            if (label) label.textContent = 'Ocultar catálogo';
            toggleCatalog?.classList.remove('is-collapsed');
        }

        const serviceBlock = field.closest('[data-quote-service]');
        if (serviceBlock) setServiceExpanded(serviceBlock, true);

        window.requestAnimationFrame(() => {
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field.focus({ preventScroll: true });
        });
    }

    quoteForm.addEventListener('invalid', (event) => {
        event.preventDefault();
    }, true);

    quoteForm.addEventListener('submit', (event) => {
        quoteForm.querySelectorAll('[data-client-validation-error]').forEach((error) => error.remove());
        quoteForm.querySelectorAll('.is-invalid').forEach((field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });

        const firstCatalogCheckbox = quoteForm.querySelector('[data-catalog-checkbox]');
        firstCatalogCheckbox?.setCustomValidity(
            servicesContainer.querySelector('[data-quote-service]')
                ? ''
                : 'Selecciona al menos un servicio del catálogo.',
        );

        const invalidFields = [...quoteForm.elements]
            .filter((field) => field.willValidate && !field.checkValidity());
        if (invalidFields.length === 0) return;

        event.preventDefault();
        invalidFields.forEach(showFieldValidation);
        showValidationSummary(invalidFields);
        focusFirstInvalidField(invalidFields[0]);
    });

    quoteForm.addEventListener('input', (event) => {
        if (event.target.matches('[data-catalog-search]')) {
            filterCatalog();
            return;
        }

        const line = event.target.closest('[data-line]');
        if (line && event.target.matches('[data-price]')) {
            line.dataset.priceEdited = 'true';
            syncManualLinePricing(line);
        }
        if (event.target.matches('[data-line-description]')) event.target.dataset.catalogAutofill = 'false';
        if (event.target.matches('[data-price-justification]')) {
            event.target.dataset.catalogAutofill = 'false';
            event.target.dataset.autoJustification = 'false';
        }
        if (event.target.willValidate && event.target.checkValidity()) {
            clearFieldValidation(event.target);
        }
        calculate();
    });

    quoteForm.addEventListener('change', (event) => {
        if (event.target.matches('[data-catalog-type-filter]')) {
            filterCatalog();
            return;
        }

        if (event.target.matches('[data-client-select]')) {
            updatePlantOptions();
        }

        if (event.target.matches('[data-client-select], [data-contact-select], [data-quote-plant-select]')) {
            calculate();
        }

        if (event.target.matches('[data-catalog-checkbox]')) {
            const firstCatalogCheckbox = quoteForm.querySelector('[data-catalog-checkbox]');
            firstCatalogCheckbox?.setCustomValidity('');
            if (firstCatalogCheckbox) clearFieldValidation(firstCatalogCheckbox);
            if (event.target.checked) {
                addCatalogService(event.target.value);
            } else {
                removeServiceBlock(serviceBlockForCatalog(event.target.value));
            }
            return;
        }

        const serviceBlock = event.target.closest('[data-quote-service]');
        if (!serviceBlock) return;

        const line = event.target.closest('[data-line]');
        if (!line) return;
        if (event.target.matches('[data-price-method]')) {
            event.target.dataset.userSelected = 'true';
            configureLine(line);
        }
        if (event.target.matches('[data-normalized-criterion]')) {
            const config = pricing[catalogId(serviceBlock)];
            if (config?.normalizacion_disponible) recalculateNormalized(line, config);
        }
    });

    quoteForm.addEventListener('click', (event) => {
        const toggleCatalog = event.target.closest('[data-toggle-service-catalog]');
        if (toggleCatalog) {
            const willOpen = catalog.hidden;
            catalog.hidden = !willOpen;
            toggleCatalog.setAttribute('aria-expanded', String(willOpen));
            const label = toggleCatalog.querySelector('[data-catalog-toggle-label]');
            if (label) label.textContent = willOpen ? 'Ocultar catálogo' : 'Mostrar catálogo';
            toggleCatalog.classList.toggle('is-collapsed', !willOpen);
            return;
        }

        const toggleService = event.target.closest('[data-toggle-quote-service]');
        if (toggleService) {
            const serviceBlock = toggleService.closest('[data-quote-service]');
            const expanded = toggleService.getAttribute('aria-expanded') !== 'true';
            setServiceExpanded(serviceBlock, expanded, { focus: expanded });
            return;
        }

        const removeServiceButton = event.target.closest('[data-remove-quote-service]');
        if (removeServiceButton) {
            removeServiceBlock(removeServiceButton.closest('[data-quote-service]'));
            return;
        }

        const addGeneralCost = event.target.closest('[data-add-general-cost]');
        if (addGeneralCost) {
            const preset = generalCostPresets[addGeneralCost.dataset.addGeneralCost]
                || generalCostPresets.otro;
            const html = generalCostTemplate.innerHTML
                .replaceAll('__COST_INDEX__', generalCostIndex++)
                .replaceAll('__COST_CLASS__', preset.className)
                .replaceAll('__COST_DESCRIPTION__', preset.description)
                .replaceAll('__COST_JUSTIFICATION__', preset.justification);
            generalCostsContainer.insertAdjacentHTML('beforeend', html);
            const cost = generalCostsContainer.querySelector('[data-general-cost]:last-of-type');
            calculate();
            const focusTarget = preset.description
                ? cost?.querySelector('[data-general-cost-value]')
                : cost?.querySelector('[data-general-cost-description]');
            focusTarget?.focus();
            return;
        }

        const removeGeneralCost = event.target.closest('[data-remove-general-cost]');
        if (removeGeneralCost) {
            removeGeneralCost.closest('[data-general-cost]')?.remove();
            calculate();
            return;
        }

        const addLine = event.target.closest('[data-add-preset-line], [data-add-line]');
        if (addLine) {
            const serviceBlock = addLine.closest('[data-quote-service]');
            const lines = serviceBlock.querySelector('[data-lines]');
            const currentServiceIndex = serviceBlock
                .querySelector('[data-operational-service]')
                .name.match(/^servicios\[([^\]]+)]/)?.[1];
            const lineIndexes = [...lines.querySelectorAll('[data-price-method]')]
                .map((field) => Number(field.name.match(/\[partidas]\[(\d+)]/)?.[1] ?? -1));
            const lineIndex = Math.max(-1, ...lineIndexes) + 1;
            const html = lineTemplate.innerHTML
                .replaceAll('__SERVICE_INDEX__', currentServiceIndex)
                .replaceAll('__LINE_INDEX__', lineIndex);
            lines.insertAdjacentHTML('beforeend', html);
            const line = lines.querySelector('[data-line]:last-child');
            line.dataset.priceEdited = 'false';
            configureLine(line);
            applyLinePreset(line, addLine.dataset.addPresetLine || 'otro');
            calculate();
            return;
        }

        const toggleNote = event.target.closest('[data-toggle-line-note]');
        if (toggleNote) {
            const line = toggleNote.closest('[data-line]');
            const note = line?.querySelector('[data-line-note]');
            if (!note) return;
            const willOpen = note.hidden;
            note.hidden = !willOpen;
            toggleNote.setAttribute('aria-expanded', String(willOpen));
            const label = toggleNote.querySelector('[data-note-action-label]');
            if (label) label.textContent = willOpen ? 'Ocultar nota' : 'Añadir nota o criterio';
            if (willOpen) note.querySelector('[data-price-justification]')?.focus();
            return;
        }

        const useReference = event.target.closest('[data-use-reference]');
        if (useReference) {
            const line = useReference.closest('[data-line]');
            const serviceBlock = line?.closest('[data-quote-service]');
            const config = serviceBlock ? pricing[catalogId(serviceBlock)] : null;
            if (!line || !config?.referencia_disponible) return;
            const price = line.querySelector('[data-price]');
            const suggested = line.querySelector('[data-suggested]');
            const method = line.querySelector('[data-price-method]');
            const justification = line.querySelector('[data-price-justification]');
            price.value = Number(config.precio_base).toFixed(2);
            suggested.value = Number(config.precio_base).toFixed(2);
            method.value = 'referencia';
            line.dataset.priceEdited = 'false';
            if (justification) {
                justification.value = 'Referencia económica configurada en el catálogo de servicios.';
                justification.dataset.catalogAutofill = 'true';
                justification.dataset.autoJustification = 'true';
            }
            calculate();
            return;
        }

        const removeLine = event.target.closest('[data-remove-line]');
        if (removeLine) {
            const lines = removeLine.closest('[data-lines]');
            if (lines.querySelectorAll('[data-line]').length > 1) {
                removeLine.closest('[data-line]').remove();
                calculate();
            }
        }
    });

    document.addEventListener('quote:client-changed', () => {
        updatePlantOptions();
        updateCatalogLinks();
        servicesContainer.querySelectorAll('[data-quote-service]').forEach((serviceBlock) => {
            configureService(serviceBlock);
        });
        calculate();
    });

    servicesContainer.querySelectorAll('[data-quote-service]').forEach((serviceBlock, index) => {
        serviceBlock.querySelectorAll('[data-line]').forEach((line) => {
            line.dataset.priceEdited = line.querySelector('[data-price]').value ? 'true' : 'false';
            if (!line.hasAttribute('data-primary-line')) syncManualLinePricing(line);
        });
        configureService(serviceBlock);
        setServiceExpanded(serviceBlock, index === 0);
    });
    updateCatalogLinks();
    updatePlantOptions();
    updateServiceSelectionUi();
    filterCatalog();
    calculate();
}

}

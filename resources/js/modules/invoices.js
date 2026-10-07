export function initInvoices() {
    const form = document.querySelector('[data-invoice-form]');
    if (!form) return;

    const combobox = form.querySelector('[data-invoice-combobox]');
    const orderInput = form.querySelector('[data-invoice-order-combobox]');
    const orderIdInput = form.querySelector('[data-invoice-order]');
    const orderList = form.querySelector('[data-invoice-order-list]');
    const orderToggle = form.querySelector('[data-invoice-order-toggle]');
    const orderEmpty = form.querySelector('[data-invoice-order-empty]');
    const orderOptions = [...form.querySelectorAll('[data-invoice-order-option]')];
    const netInput = form.querySelector('[data-invoice-net]');
    const netDisplay = form.querySelector('[data-invoice-net-display]');
    const numberFormat = new Intl.NumberFormat('es-CL', {
        maximumFractionDigits: 0,
    });

    const setText = (selector, value) => {
        const element = form.querySelector(selector);
        if (element) element.textContent = value;
    };
    const money = (value, currency) => `${currency} $${numberFormat.format(value)}`;
    const selectedOrder = () => orderOptions.find(
        (option) => option.dataset.value === orderIdInput?.value,
    );

    function setNetAmount(value) {
        if (!netInput || !netDisplay) return;

        const rounded = Math.round(Number(value || 0) * 100) / 100;
        netInput.value = String(rounded);
        netDisplay.value = numberFormat.format(rounded);
    }

    function updateInvoiceSummary({ prefillNet = false } = {}) {
        const option = selectedOrder();
        const hasOrder = Boolean(option);

        if (!hasOrder) {
            setText('[data-invoice-organization]', 'Selecciona una OC');
            setText('[data-invoice-order-amount]', '—');
            setText('[data-invoice-billed]', '—');
            setText('[data-invoice-balance]', '—');
            setText('[data-invoice-tax-rate]', '—');
            setText('[data-invoice-tax]', '—');
            setText('[data-invoice-total]', '—');
            setText('[data-invoice-resulting-balance]', '—');
            const warning = form.querySelector('[data-invoice-warning]');
            if (warning) warning.hidden = true;
            return;
        }

        const currency = option.dataset.moneda || 'CLP';
        const orderAmount = Number(option.dataset.monto || 0);
        const billed = Number(option.dataset.facturado || 0);
        const balance = Number(option.dataset.saldo || 0);
        const taxRate = Number(option.dataset.iva || 0);

        if (prefillNet) {
            setNetAmount(balance);
        }

        const net = Number(netInput?.value || 0);
        const tax = Math.round((net * taxRate / 100) * 100) / 100;
        const total = net + tax;
        const resultingBalance = balance - net;

        setText('[data-invoice-organization]', option.dataset.organizacion || '—');
        setText('[data-invoice-order-amount]', money(orderAmount, currency));
        setText('[data-invoice-billed]', money(billed, currency));
        setText('[data-invoice-balance]', money(balance, currency));
        setText('[data-invoice-tax-rate]', `${numberFormat.format(taxRate)}%`);
        setText('[data-invoice-tax]', money(tax, currency));
        setText('[data-invoice-total]', money(total, currency));
        setText('[data-invoice-resulting-balance]', money(resultingBalance, currency));
        setText('[data-invoice-net-currency]', `${currency} $`);

        const warning = form.querySelector('[data-invoice-warning]');
        if (warning) warning.hidden = net <= 0 || resultingBalance >= 0;
    }

    function openOrderList() {
        if (!orderList || !orderInput) return;

        orderList.hidden = false;
        orderInput.setAttribute('aria-expanded', 'true');
    }

    function closeOrderList() {
        if (!orderList || !orderInput) return;

        orderList.hidden = true;
        orderInput.setAttribute('aria-expanded', 'false');
    }

    function filterOrders() {
        const term = orderInput?.value.trim().toLocaleLowerCase('es') || '';
        let visibleCount = 0;

        orderOptions.forEach((option) => {
            const matches = option.dataset.label.toLocaleLowerCase('es').includes(term);
            option.hidden = !matches;
            if (matches) visibleCount += 1;
        });

        if (orderEmpty) orderEmpty.hidden = visibleCount > 0;
    }

    function chooseOrder(option, { prefillNet = true } = {}) {
        if (!orderInput || !orderIdInput) return;

        orderIdInput.value = option.dataset.value;
        orderInput.value = option.dataset.label;
        orderOptions.forEach((item) => {
            item.setAttribute('aria-selected', item === option ? 'true' : 'false');
            item.hidden = false;
        });
        closeOrderList();
        updateInvoiceSummary({ prefillNet });
    }

    orderInput?.addEventListener('focus', () => {
        orderInput.select();
        filterOrders();
        openOrderList();
    });
    orderInput?.addEventListener('input', () => {
        if (orderIdInput) orderIdInput.value = '';
        filterOrders();
        openOrderList();
        updateInvoiceSummary();
    });
    orderInput?.addEventListener('keydown', (event) => {
        const visibleOptions = orderOptions.filter((option) => !option.hidden);

        if (event.key === 'ArrowDown' && visibleOptions[0]) {
            event.preventDefault();
            visibleOptions[0].focus();
        }
        if (event.key === 'Enter' && visibleOptions[0]) {
            event.preventDefault();
            chooseOrder(visibleOptions[0]);
        }
        if (event.key === 'Escape') closeOrderList();
    });
    orderOptions.forEach((option, index) => {
        option.addEventListener('click', () => chooseOrder(option));
        option.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                orderOptions[index + 1]?.focus();
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                (orderOptions[index - 1] || orderInput)?.focus();
            }
            if (event.key === 'Escape') {
                closeOrderList();
                orderInput?.focus();
            }
        });
    });
    orderToggle?.addEventListener('click', () => {
        if (orderList?.hidden) {
            orderInput?.focus();
            orderOptions.forEach((option) => { option.hidden = false; });
            if (orderEmpty) orderEmpty.hidden = true;
            openOrderList();
        } else {
            closeOrderList();
        }
    });
    document.addEventListener('click', (event) => {
        if (!combobox?.contains(event.target)) closeOrderList();
    });

    netDisplay?.addEventListener('input', () => {
        const digits = netDisplay.value.replace(/[^0-9]/g, '');
        const value = Number(digits || 0);

        if (netInput) netInput.value = String(value);
        netDisplay.value = value > 0 ? numberFormat.format(value) : '';
        updateInvoiceSummary();
    });

    function updatePaymentDays() {
        const condition = form.querySelector('input[name="condicion_pago"]:checked')?.value;
        const daysField = form.querySelector('[data-payment-days-field]');
        const daysInput = form.querySelector('[data-payment-days]');
        const isCredit = condition === 'credito';

        if (daysField) daysField.hidden = !isCredit;
        if (daysInput) {
            daysInput.disabled = !isCredit;
            daysInput.required = isCredit;
            if (isCredit && !daysInput.value) daysInput.value = '45';
        }
    }

    form.querySelectorAll('input[name="condicion_pago"]').forEach((input) => {
        input.addEventListener('change', updatePaymentDays);
    });

    updatePaymentDays();
    if (selectedOrder()) {
        chooseOrder(selectedOrder(), { prefillNet: netInput?.dataset.hasValue !== '1' });
    } else {
        updateInvoiceSummary();
    }

    if (netInput?.dataset.hasValue === '1') setNetAmount(netInput.value);
}

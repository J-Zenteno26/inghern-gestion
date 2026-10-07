export function initPayments() {
    const form = document.querySelector('[data-payment-form]');
    if (!form) return;

    const numberFormat = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 0 });
    const percentFormat = new Intl.NumberFormat('es-CL', { maximumFractionDigits: 2 });
    const totalInput = form.querySelector('[data-payment-total]');
    const totalDisplay = form.querySelector('[data-payment-total-display]');
    const allocationElements = [...form.querySelectorAll('[data-payment-allocation]')];
    const addAllocationButton = form.querySelector('[data-payment-add-allocation]');

    const money = (value, currency = 'CLP') => currency + ' $' + numberFormat.format(Number(value || 0));
    const numericValue = (value) => Number(String(value || '').replace(/[^0-9]/g, '') || 0);
    const percentValue = (value) => Number(String(value || '').replace(',', '.').replace(/[^0-9.]/g, '') || 0);

    function setMoneyInput(display, hidden, value) {
        const numeric = Math.max(Number(value || 0), 0);
        if (hidden) hidden.value = numeric > 0 ? String(numeric) : '';
        if (display) display.value = numeric > 0 ? numberFormat.format(numeric) : '';
    }

    function setPercentInput(input, value) {
        if (!input) return;
        const numeric = Number(value || 0);
        input.value = numeric > 0 ? String(Math.round(numeric * 100) / 100) : '';
    }

    function selectedOption(allocation) {
        const invoiceId = allocation.querySelector('[data-payment-invoice-id]')?.value;

        return [...allocation.querySelectorAll('[data-payment-invoice-option]')]
            .find((option) => option.dataset.value === invoiceId);
    }

    function activeAllocations() {
        return allocationElements.filter((allocation) => !allocation.hidden);
    }

    function hasSingleActiveAllocation(allocation) {
        const active = activeAllocations();
        return active.length === 1 && active[0] === allocation;
    }

    function setAllocationEnabled(allocation, enabled) {
        allocation.hidden = !enabled;
        allocation.querySelectorAll('input, button').forEach((control) => {
            control.disabled = !enabled;
        });
    }

    function allocationAmount(allocation) {
        return Number(allocation.querySelector('[data-payment-allocation-amount]')?.value || 0);
    }

    function updateAllocationPercentage(allocation) {
        const option = selectedOption(allocation);
        const percentageInput = allocation.querySelector('[data-payment-allocation-percent]');
        if (!option || !percentageInput) {
            setPercentInput(percentageInput, 0);
            return;
        }

        const balance = Math.max(Number(option.dataset.balance || 0), 0);
        const amount = allocationAmount(allocation);
        setPercentInput(percentageInput, balance > 0 ? (amount / balance) * 100 : 0);
    }

    function syncSingleAllocationTotal(allocation) {
        if (!hasSingleActiveAllocation(allocation)) return;
        setMoneyInput(totalDisplay, totalInput, allocationAmount(allocation));
    }

    function updateSummary() {
        const active = activeAllocations();
        const firstOption = active.map(selectedOption).find(Boolean);
        const currency = firstOption?.dataset.currency || 'CLP';
        const total = Number(totalInput?.value || 0);
        let distributed = 0;
        let hasOverpayment = false;
        const selectedInvoices = [];

        active.forEach((allocation) => {
            const option = selectedOption(allocation);
            const amount = allocationAmount(allocation);
            distributed += amount;

            const totalElement = allocation.querySelector('[data-allocation-total]');
            const paidElement = allocation.querySelector('[data-allocation-paid]');
            const balanceElement = allocation.querySelector('[data-allocation-balance]');
            const resultElement = allocation.querySelector('[data-allocation-result]');
            const pendingPercentElement = allocation.querySelector('[data-allocation-pending-percent]');
            const originalPercentElement = allocation.querySelector('[data-allocation-original-percent]');
            const currencyElement = allocation.querySelector('[data-allocation-currency]');

            if (!option) {
                [totalElement, paidElement, balanceElement, resultElement].forEach((element) => {
                    if (element) element.textContent = '—';
                });
                if (pendingPercentElement) pendingPercentElement.textContent = '—';
                if (originalPercentElement) originalPercentElement.textContent = '—';
                return;
            }

            const optionCurrency = option.dataset.currency || currency;
            const invoiceTotal = Number(option.dataset.total || 0);
            const previouslyPaid = Number(option.dataset.paid || 0);
            const balance = Number(option.dataset.balance || 0);
            const resultingBalance = balance - amount;
            const percentOfBalance = balance > 0 ? (amount / balance) * 100 : 0;
            const percentOfOriginal = invoiceTotal > 0 ? (amount / invoiceTotal) * 100 : 0;
            const pendingPercent = balance > 0 ? Math.max((resultingBalance / balance) * 100, 0) : 0;
            hasOverpayment ||= resultingBalance < 0;

            if (currencyElement) currencyElement.textContent = optionCurrency + ' $';
            if (totalElement) totalElement.textContent = money(invoiceTotal, optionCurrency);
            if (paidElement) paidElement.textContent = money(previouslyPaid, optionCurrency);
            if (balanceElement) balanceElement.textContent = money(balance, optionCurrency);
            if (resultElement) resultElement.textContent = money(resultingBalance, optionCurrency);
            if (pendingPercentElement) {
                pendingPercentElement.textContent = resultingBalance <= 0
                    ? '0% pendiente'
                    : percentFormat.format(pendingPercent) + '% pendiente';
            }
            if (originalPercentElement) {
                originalPercentElement.textContent = amount > 0
                    ? 'Equivale al ' + percentFormat.format(percentOfOriginal) + '% del total original'
                    : '—';
            }

            selectedInvoices.push({
                folio: option.dataset.folio,
                organization: option.dataset.organization,
                amount,
                balance: resultingBalance,
                currency: optionCurrency,
                percentOfBalance,
            });
        });

        const remaining = total - distributed;
        const totalSummary = form.querySelector('[data-payment-summary-total]');
        const distributedSummary = form.querySelector('[data-payment-summary-distributed]');
        const remainingSummary = form.querySelector('[data-payment-summary-remaining]');
        const totalCurrency = form.querySelector('[data-payment-total-currency]');

        if (totalCurrency) totalCurrency.textContent = currency + ' $';
        if (totalSummary) totalSummary.textContent = money(total, currency);
        if (distributedSummary) distributedSummary.textContent = money(distributed, currency);
        if (remainingSummary) {
            remainingSummary.textContent = money(remaining, currency);
            remainingSummary.classList.toggle('is-balanced', total > 0 && Math.abs(remaining) < 0.01);
            remainingSummary.classList.toggle('has-difference', Math.abs(remaining) >= 0.01);
        }

        const summaryInvoices = form.querySelector('[data-payment-summary-invoices]');
        if (summaryInvoices) {
            summaryInvoices.replaceChildren();
            if (selectedInvoices.length === 0) {
                const empty = document.createElement('p');
                empty.textContent = 'Selecciona al menos una factura.';
                summaryInvoices.append(empty);
            } else {
                selectedInvoices.forEach((invoice) => {
                    const item = document.createElement('div');
                    const identity = document.createElement('span');
                    const folio = document.createElement('strong');
                    const organization = document.createElement('small');
                    const result = document.createElement('span');
                    const amount = document.createElement('strong');
                    const percentage = document.createElement('small');
                    const balance = document.createElement('small');

                    folio.textContent = invoice.folio;
                    organization.textContent = invoice.organization;
                    amount.textContent = money(invoice.amount, invoice.currency);
                    percentage.textContent = percentFormat.format(invoice.percentOfBalance) + '% del saldo';
                    balance.textContent = 'Saldo posterior ' + money(invoice.balance, invoice.currency);

                    identity.append(folio, organization);
                    result.append(amount, percentage, balance);
                    item.append(identity, result);
                    summaryInvoices.append(item);
                });
            }
        }

        const warning = form.querySelector('[data-payment-overpayment-warning]');
        if (warning) warning.hidden = !hasOverpayment;
    }

    function setupCombobox(allocation) {
        const combobox = allocation.querySelector('[data-payment-combobox]');
        const search = allocation.querySelector('[data-payment-invoice-search]');
        const invoiceId = allocation.querySelector('[data-payment-invoice-id]');
        const list = allocation.querySelector('[data-payment-invoice-list]');
        const toggle = allocation.querySelector('[data-payment-invoice-toggle]');
        const empty = allocation.querySelector('[data-payment-invoice-empty]');
        const options = [...allocation.querySelectorAll('[data-payment-invoice-option]')];
        const amountInput = allocation.querySelector('[data-payment-allocation-amount]');
        const amountDisplay = allocation.querySelector('[data-payment-allocation-display]');
        const percentageInput = allocation.querySelector('[data-payment-allocation-percent]');

        const open = () => {
            if (!list || !search) return;
            list.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        };

        const close = () => {
            if (!list || !search) return;
            list.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        };

        const filter = () => {
            const term = search?.value.trim().toLocaleLowerCase('es') || '';
            let visible = 0;
            options.forEach((option) => {
                const matches = option.dataset.label.toLocaleLowerCase('es').includes(term);
                option.hidden = !matches;
                if (matches) visible += 1;
            });
            if (empty) empty.hidden = visible > 0;
        };

        const choose = (option, prefill = true) => {
            if (!search || !invoiceId) return;

            search.value = option.dataset.label;
            invoiceId.value = option.dataset.value;
            options.forEach((item) => {
                item.hidden = false;
                item.setAttribute('aria-selected', item === option ? 'true' : 'false');
            });

            const balance = Math.max(Number(option.dataset.balance || 0), 0);
            if (prefill && !amountInput?.value) {
                if (allocation.dataset.allocationIndex === '0' && hasSingleActiveAllocation(allocation)) {
                    setMoneyInput(amountDisplay, amountInput, balance);
                    setMoneyInput(totalDisplay, totalInput, balance);
                } else {
                    const total = Number(totalInput?.value || 0);
                    const otherDistributed = activeAllocations()
                        .filter((item) => item !== allocation)
                        .reduce((sum, item) => sum + allocationAmount(item), 0);
                    const remaining = Math.max(total - otherDistributed, 0);
                    setMoneyInput(amountDisplay, amountInput, Math.min(remaining, balance));
                }
            }

            updateAllocationPercentage(allocation);
            close();
            updateSummary();
        };

        search?.addEventListener('focus', () => {
            search.select();
            filter();
            open();
        });

        search?.addEventListener('input', () => {
            if (invoiceId) invoiceId.value = '';
            setMoneyInput(amountDisplay, amountInput, 0);
            setPercentInput(percentageInput, 0);
            filter();
            open();
            updateSummary();
        });

        search?.addEventListener('keydown', (event) => {
            const visibleOptions = options.filter((option) => !option.hidden);
            if (event.key === 'Enter' && visibleOptions[0]) {
                event.preventDefault();
                choose(visibleOptions[0]);
            }
            if (event.key === 'ArrowDown' && visibleOptions[0]) {
                event.preventDefault();
                visibleOptions[0].focus();
            }
            if (event.key === 'Escape') close();
        });

        toggle?.addEventListener('click', () => {
            if (list?.hidden) {
                search?.focus();
                options.forEach((option) => {
                    option.hidden = false;
                });
                if (empty) empty.hidden = options.length > 0;
                open();
            } else {
                close();
            }
        });

        options.forEach((option) => option.addEventListener('click', () => choose(option)));

        document.addEventListener('click', (event) => {
            if (!combobox?.contains(event.target)) close();
        });

        percentageInput?.addEventListener('input', () => {
            const option = selectedOption(allocation);
            if (!option) return;
            const balance = Math.max(Number(option.dataset.balance || 0), 0);
            const percentage = Math.max(percentValue(percentageInput.value), 0);
            const amount = balance * (percentage / 100);
            setMoneyInput(amountDisplay, amountInput, amount);
            syncSingleAllocationTotal(allocation);
            updateSummary();
        });

        amountDisplay?.addEventListener('input', () => {
            setMoneyInput(amountDisplay, amountInput, numericValue(amountDisplay.value));
            updateAllocationPercentage(allocation);
            syncSingleAllocationTotal(allocation);
            updateSummary();
        });

        if (amountInput?.value) setMoneyInput(amountDisplay, amountInput, amountInput.value);
        const initialOption = selectedOption(allocation);
        if (initialOption) choose(initialOption, !amountInput?.value);
        updateAllocationPercentage(allocation);
    }

    allocationElements.forEach(setupCombobox);

    totalDisplay?.addEventListener('input', () => {
        setMoneyInput(totalDisplay, totalInput, numericValue(totalDisplay.value));

        const active = activeAllocations();
        if (active.length === 1) {
            const allocation = active[0];
            if (selectedOption(allocation)) {
                setMoneyInput(
                    allocation.querySelector('[data-payment-allocation-display]'),
                    allocation.querySelector('[data-payment-allocation-amount]'),
                    Number(totalInput?.value || 0),
                );
                updateAllocationPercentage(allocation);
            }
        }

        updateSummary();
    });

    if (totalInput?.value) setMoneyInput(totalDisplay, totalInput, totalInput.value);

    addAllocationButton?.addEventListener('click', () => {
        const second = allocationElements[1];
        if (!second) return;
        setAllocationEnabled(second, true);
        addAllocationButton.hidden = true;
        second.querySelector('[data-payment-invoice-search]')?.focus();
        updateSummary();
    });

    allocationElements[1]?.querySelector('[data-payment-remove-allocation]')?.addEventListener('click', () => {
        const second = allocationElements[1];
        second.querySelector('[data-payment-invoice-search]').value = '';
        second.querySelector('[data-payment-invoice-id]').value = '';
        setMoneyInput(
            second.querySelector('[data-payment-allocation-display]'),
            second.querySelector('[data-payment-allocation-amount]'),
            0,
        );
        setPercentInput(second.querySelector('[data-payment-allocation-percent]'), 0);
        setAllocationEnabled(second, false);
        if (addAllocationButton) addAllocationButton.hidden = false;

        const first = allocationElements[0];
        if (first && selectedOption(first)) {
            setMoneyInput(totalDisplay, totalInput, allocationAmount(first));
            updateAllocationPercentage(first);
        }

        updateSummary();
    });

    updateSummary();
}

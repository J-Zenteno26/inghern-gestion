import { initOrganizations } from './modules/organizations.js';
import { initDocuments } from './modules/documents.js';
import { initInvoices } from './modules/invoices.js';
import { initPayments } from './modules/payments.js';
import { initPriceReferences } from './modules/price-references.js';
import { initQuotes } from './modules/quotes.js';
import { initServices } from './modules/services.js';

function initFilterForms() {
    document.querySelectorAll('[data-filter-form]').forEach((form) => {
        const client = form.querySelector('[data-filter-client]');
        const plant = form.querySelector('[data-filter-plant]');

        const updatePlants = () => {
            if (!plant) return;

            const clientId = client?.value || '';
            let selectedVisible = !plant.value;
            [...plant.options].forEach((option, index) => {
                const visible = index === 0 || !clientId || option.dataset.client === clientId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (visible && option.selected) selectedVisible = true;
            });

            if (!selectedVisible) plant.value = '';
        };

        client?.addEventListener('change', updatePlants);
        form.querySelectorAll('[data-auto-submit]').forEach((control) => {
            control.addEventListener('change', () => form.requestSubmit());
        });
        updatePlants();
    });
}

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-menu-toggle]')) {
        document.querySelector('[data-main-menu]')?.classList.toggle('is-open');
    }
    if (event.target.closest('[data-dismiss-flash]')) {
        event.target.closest('[data-flash]')?.remove();
    }
});

initServices();
initDocuments();
initPriceReferences();
initQuotes();
initOrganizations();
initInvoices();
initPayments();
initFilterForms();

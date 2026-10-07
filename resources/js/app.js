import { initOrganizations } from './modules/organizations.js';
import { initInvoices } from './modules/invoices.js';
import { initPayments } from './modules/payments.js';
import { initPriceReferences } from './modules/price-references.js';
import { initQuotes } from './modules/quotes.js';
import { initServices } from './modules/services.js';

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-menu-toggle]')) {
        document.querySelector('[data-main-menu]')?.classList.toggle('is-open');
    }
    if (event.target.closest('[data-dismiss-flash]')) {
        event.target.closest('[data-flash]')?.remove();
    }
});

initServices();
initPriceReferences();
initQuotes();
initOrganizations();
initInvoices();
initPayments();

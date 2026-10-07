export function initOrganizations() {
function setupOrganizationAccordions() {
    document.querySelectorAll('[data-org-accordion]').forEach((accordion) => {
        const toggle = accordion.querySelector('[data-org-accordion-toggle]');
        const content = accordion.querySelector('[data-org-accordion-content]');
        if (!toggle || !content) return;

        toggle.addEventListener('click', () => {
            const isOpen = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!isOpen));
            content.hidden = isOpen;
        });
    });
}

function normalizeOrganizationFilter(value = '') {
    return value
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function setupOrganizationServices() {
    const root = document.querySelector('[data-org-services]');
    if (!root) return;

    const rows = [...root.querySelectorAll('[data-org-service-row]')];
    const search = root.querySelector('[data-org-services-search]');
    const type = root.querySelector('[data-org-services-type]');
    const status = root.querySelector('[data-org-services-status]');
    const clear = root.querySelector('[data-org-services-clear]');
    const empty = root.querySelector('[data-org-services-empty]');
    const count = root.querySelector('[data-org-services-count]');

    const filter = () => {
        const query = normalizeOrganizationFilter(search?.value || '');
        const selectedType = type?.value || '';
        const selectedStatus = status?.value || '';
        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch = normalizeOrganizationFilter(row.dataset.search || '').includes(query);
            const matchesType = !selectedType || row.dataset.type === selectedType;
            const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
            const show = matchesSearch && matchesType && matchesStatus;

            row.hidden = !show;
            if (show) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
        if (count) count.textContent = String(visible);
    };

    search?.addEventListener('input', filter);
    type?.addEventListener('change', filter);
    status?.addEventListener('change', filter);
    clear?.addEventListener('click', () => {
        if (search) search.value = '';
        if (type) type.value = '';
        if (status) status.value = '';
        filter();
        search?.focus();
    });
}

function setupOrganizationQuotes() {
    const root = document.querySelector('[data-org-quotes]');
    if (!root) return;

    const rows = [...root.querySelectorAll('[data-org-quote-row]')];
    const search = root.querySelector('[data-org-quotes-search]');
    const status = root.querySelector('[data-org-quotes-status]');
    const clear = root.querySelector('[data-org-quotes-clear]');
    const empty = root.querySelector('[data-org-quotes-empty]');
    const count = root.querySelector('[data-org-quotes-count]');

    const filter = () => {
        const query = normalizeOrganizationFilter(search?.value || '');
        const selectedStatus = status?.value || '';
        let visible = 0;

        rows.forEach((row) => {
            const matchesSearch = normalizeOrganizationFilter(row.dataset.search || '').includes(query);
            const matchesStatus = !selectedStatus || row.dataset.status === selectedStatus;
            const show = matchesSearch && matchesStatus;

            row.hidden = !show;
            if (show) visible += 1;
        });

        if (empty) empty.hidden = visible !== 0;
        if (count) count.textContent = String(visible);
    };

    search?.addEventListener('input', filter);
    status?.addEventListener('change', filter);
    clear?.addEventListener('click', () => {
        if (search) search.value = '';
        if (status) status.value = '';
        filter();
        search?.focus();
    });
}

setupOrganizationAccordions();
setupOrganizationServices();
setupOrganizationQuotes();
}



export function initPriceReferences() {
const priceReferences = document.querySelector('[data-price-references]');
if (priceReferences) {
    const groups = [...priceReferences.querySelectorAll('[data-price-group]')];
    const items = [...priceReferences.querySelectorAll('[data-price-item]')];
    const search = priceReferences.querySelector('[data-price-search]');
    const status = priceReferences.querySelector('[data-price-status]');
    const noResults = priceReferences.querySelector('[data-price-no-results]');

    const normalize = (value) => value
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    function setEditorOpen(item, open) {
        const editor = item.querySelector('[data-price-editor]');
        const toggle = item.querySelector('[data-price-edit]');

        editor.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        item.classList.toggle('is-editing', open);
    }

    function closeEditors(except = null) {
        items.forEach((item) => {
            if (item !== except) setEditorOpen(item, false);
        });
    }

    function setGroupOpen(group, open) {
        if (open) {
            groups.forEach((other) => {
                if (other === group) return;

                other.querySelector('[data-price-group-panel]').hidden = true;
                other.querySelector('[data-price-group-toggle]')
                    .setAttribute('aria-expanded', 'false');
                other.querySelectorAll('[data-price-item]')
                    .forEach((item) => setEditorOpen(item, false));
            });
        } else {
            group.querySelectorAll('[data-price-item]')
                .forEach((item) => setEditorOpen(item, false));
        }

        group.querySelector('[data-price-group-panel]').hidden = !open;
        group.querySelector('[data-price-group-toggle]')
            .setAttribute('aria-expanded', String(open));
    }

    function filterReferences() {
        const query = normalize(search.value);
        const selectedStatus = status.value;
        const filtering = Boolean(query) || selectedStatus !== 'todos';
        const visibleGroups = [];

        groups.forEach((group) => {
            const groupItems = [...group.querySelectorAll('[data-price-item]')];
            let matches = 0;

            groupItems.forEach((item) => {
                const matchesSearch = normalize(item.dataset.search).includes(query);
                const configured = item.dataset.configured === 'true';
                const matchesStatus = selectedStatus === 'todos'
                    || (selectedStatus === 'configurado' && configured)
                    || (selectedStatus === 'sin_precio' && !configured);
                const visible = matchesSearch && matchesStatus;

                item.hidden = !visible;
                if (visible) {
                    matches++;
                } else {
                    setEditorOpen(item, false);
                }
            });

            const showEmptyGroup = group.dataset.emptyGroup === 'true' && !filtering;
            group.hidden = matches === 0 && !showEmptyGroup;
            if (!group.hidden) visibleGroups.push({ group, matches });
            if (group.hidden) setGroupOpen(group, false);
        });

        noResults.hidden = visibleGroups.length > 0;

        if (filtering) {
            const firstMatch = visibleGroups.find(({ matches }) => matches > 0);
            if (firstMatch) setGroupOpen(firstMatch.group, true);
        }
    }

    priceReferences.addEventListener('click', (event) => {
        const groupToggle = event.target.closest('[data-price-group-toggle]');
        if (groupToggle) {
            const group = groupToggle.closest('[data-price-group]');
            setGroupOpen(group, groupToggle.getAttribute('aria-expanded') !== 'true');
            return;
        }

        const editToggle = event.target.closest('[data-price-edit]');
        if (editToggle) {
            const item = editToggle.closest('[data-price-item]');
            const group = item.closest('[data-price-group]');
            const open = editToggle.getAttribute('aria-expanded') !== 'true';

            setGroupOpen(group, true);
            closeEditors(item);
            setEditorOpen(item, open);
            if (open) item.querySelector('textarea')?.focus();
            return;
        }

        const cancel = event.target.closest('[data-price-cancel]');
        if (cancel) {
            setEditorOpen(cancel.closest('[data-price-item]'), false);
            return;
        }

        if (event.target.closest('[data-price-clear]')) {
            search.value = '';
            status.value = 'todos';
            filterReferences();
        }
    });

    search.addEventListener('input', filterReferences);
    status.addEventListener('change', filterReferences);

    const errorCatalogId = priceReferences.dataset.errorCatalog;
    const errorItem = items.find((item) => item.dataset.catalogId === errorCatalogId);
    if (errorItem) {
        setGroupOpen(errorItem.closest('[data-price-group]'), true);
        closeEditors(errorItem);
        setEditorOpen(errorItem, true);
    } else {
        const savedCatalogId = location.hash.match(/^#catalogo-(\d+)$/)?.[1];
        const savedItem = items.find((item) => item.dataset.catalogId === savedCatalogId);
        if (savedItem) {
            setGroupOpen(savedItem.closest('[data-price-group]'), true);
            requestAnimationFrame(() => savedItem.scrollIntoView({ block: 'nearest' }));
        }
    }
}

}



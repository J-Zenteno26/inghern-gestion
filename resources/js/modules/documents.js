export function initDocuments() {
    document.querySelectorAll('[data-document-file]').forEach((input) => {
        const name = input.parentElement?.querySelector('[data-document-file-name]');
        const updateName = () => {
            if (name) name.textContent = input.files?.[0]?.name || 'Ningún archivo seleccionado';
        };

        input.addEventListener('change', updateName);
        updateName();
    });

    document.querySelectorAll('[data-document-link-form]').forEach((form) => {
        const client = form.querySelector('[data-document-client]');
        const type = form.querySelector('[data-document-link-type]');
        const entity = form.querySelector('[data-document-link-entity]');
        const combobox = form.querySelector('[data-document-entity-combobox]');
        const search = form.querySelector('[data-document-entity-search]');
        const toggle = form.querySelector('[data-document-entity-toggle]');
        const list = form.querySelector('[data-document-entity-list]');
        const empty = form.querySelector('[data-document-entity-empty]');
        if (!type || !entity) return;

        const options = [...form.querySelectorAll('[data-document-entity-option]')];

        const close = () => {
            if (!list || !search) return;
            list.hidden = true;
            search.setAttribute('aria-expanded', 'false');
        };

        const open = () => {
            if (!list || !search || search.disabled) return;
            list.hidden = false;
            search.setAttribute('aria-expanded', 'true');
        };

        const filter = (applySearch = true) => {
            const clientId = client?.value || form.dataset.fixedClient || '';
            const selectedType = type.value;
            const term = applySearch ? search?.value.trim().toLocaleLowerCase('es') || '' : '';
            let visible = 0;

            options.forEach((option) => {
                const belongsToSelection = option.dataset.client === clientId
                    && option.dataset.type === selectedType;
                const matchesSearch = option.dataset.label.toLocaleLowerCase('es').includes(term);
                const show = belongsToSelection && matchesSearch;
                option.hidden = !show;
                if (show) visible += 1;
            });

            const available = options.filter((option) => (
                option.dataset.client === clientId && option.dataset.type === selectedType
            ));
            const enabled = Boolean(clientId && selectedType && available.length);
            const selected = options.find((option) => option.dataset.value === entity.value);

            if (selected && !available.includes(selected)) {
                entity.value = '';
                if (search) search.value = '';
                options.forEach((option) => option.setAttribute('aria-selected', 'false'));
            }

            entity.disabled = !enabled;
            if (search) {
                search.disabled = !enabled;
                search.placeholder = enabled
                    ? 'Buscar por código, título, organización o planta'
                    : 'Selecciona primero una organización y un tipo de vínculo';
            }
            if (toggle) toggle.disabled = !enabled;
            if (empty) empty.hidden = visible > 0;

            if (!enabled) close();
        };

        const choose = (option) => {
            if (!search) return;

            entity.value = option.dataset.value;
            search.value = option.dataset.label;
            options.forEach((item) => {
                item.setAttribute('aria-selected', item === option ? 'true' : 'false');
            });
            close();
        };

        search?.addEventListener('focus', () => {
            search.select();
            filter();
            open();
        });

        search?.addEventListener('input', () => {
            entity.value = '';
            options.forEach((option) => option.setAttribute('aria-selected', 'false'));
            filter();
            open();
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
                if (search) search.value = '';
                filter(false);
                search?.focus();
                open();
            } else {
                close();
            }
        });

        options.forEach((option) => option.addEventListener('click', () => choose(option)));

        document.addEventListener('click', (event) => {
            if (!combobox?.contains(event.target)) close();
        });

        const resetAndFilter = () => {
            entity.value = '';
            if (search) search.value = '';
            options.forEach((option) => option.setAttribute('aria-selected', 'false'));
            filter(false);
        };

        client?.addEventListener('change', resetAndFilter);
        type.addEventListener('change', resetAndFilter);
        filter(false);
    });
}

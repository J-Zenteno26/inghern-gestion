export function initServices() {
const clientSelect = document.querySelector('[data-client-select]');
const contactSelect = document.querySelector('[data-contact-select]');
const plantContainer = document.querySelector('[data-plants]');

function updateClientDependents() {
    const clientId = clientSelect?.value || '';
    if (contactSelect) {
        let selectedVisible = !contactSelect.value;
        [...contactSelect.options].forEach((option, index) => {
            const visible = index === 0 || option.dataset.client === clientId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && option.selected) selectedVisible = true;
        });
        if (!selectedVisible) contactSelect.value = '';
    }
    if (plantContainer) {
        const groups = [...plantContainer.querySelectorAll('[data-client]')];
        groups.forEach((group) => { group.hidden = group.dataset.client !== clientId; });
        const noPlants = plantContainer.querySelector('[data-no-plants]');
        if (noPlants) noPlants.hidden = groups.some((group) => !group.hidden);
    }
    document.dispatchEvent(new CustomEvent('quote:client-changed'));
}

clientSelect?.addEventListener('change', updateClientDependents);
updateClientDependents();

const serviceTypeSelect = document.querySelector('[data-service-type-select]');
const serviceCatalogSelect = document.querySelector('[data-service-catalog-select]');
const serviceOtherField = document.querySelector('[data-service-other-field]');
const serviceOtherInput = document.querySelector('[data-service-other-input]');

function updateServiceCatalog() {
    if (!serviceCatalogSelect) return;

    const typeId = serviceTypeSelect?.value || '';
    let selectedVisible = !serviceCatalogSelect.value;

    [...serviceCatalogSelect.options].forEach((option, index) => {
        const isPlaceholder = index === 0;
        const isOther = option.hasAttribute('data-service-other-option');
        const visible = isPlaceholder
            || (Boolean(typeId) && (isOther || option.dataset.serviceType === typeId));

        option.hidden = !visible;
        option.disabled = !visible;

        if (visible && option.selected) selectedVisible = true;
    });

    if (!selectedVisible) serviceCatalogSelect.value = '';

    const isOther = serviceCatalogSelect.value === 'otro';
    if (serviceOtherField) serviceOtherField.hidden = !isOther;
    if (serviceOtherInput) {
        serviceOtherInput.required = isOther;
        if (!isOther) serviceOtherInput.value = '';
    }
}

serviceTypeSelect?.addEventListener('change', updateServiceCatalog);
serviceCatalogSelect?.addEventListener('change', updateServiceCatalog);
updateServiceCatalog();
}

(() => {
    const addButton = document.querySelector('[data-add-benefit]');
    const container = document.querySelector('[data-benefits]');
    const template = document.querySelector('#benefit-template');

    if (addButton && container && template) {
        addButton.addEventListener('click', () => {
            container.appendChild(template.content.cloneNode(true));
        });

        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-benefit]');
            if (!button) return;

            const rows = container.querySelectorAll('.benefit-row');
            if (rows.length <= 1) {
                rows[0].querySelectorAll('input').forEach((input) => input.value = '');
                return;
            }

            button.closest('.benefit-row')?.remove();
        });
    }
})();

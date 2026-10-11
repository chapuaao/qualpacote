(() => {
    const results = document.getElementById('resultados');
    if (results && new URLSearchParams(window.location.search).has('compare')) {
        requestAnimationFrame(() => {
            results.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    const wireRepeater = ({ addSelector, containerSelector, templateSelector, removeSelector, rowSelector }) => {
        const addButton = document.querySelector(addSelector);
        const container = document.querySelector(containerSelector);
        const template = document.querySelector(templateSelector);
        if (!addButton || !container || !template) return;

        addButton.addEventListener('click', () => container.appendChild(template.content.cloneNode(true)));
        container.addEventListener('click', (event) => {
            const button = event.target.closest(removeSelector);
            if (!button) return;
            const rows = container.querySelectorAll(rowSelector);
            if (rows.length <= 1) {
                rows[0].querySelectorAll('input').forEach((input) => input.value = '');
                return;
            }
            button.closest(rowSelector)?.remove();
        });
    };

    wireRepeater({
        addSelector: '[data-add-benefit]',
        containerSelector: '[data-benefits]',
        templateSelector: '#benefit-template',
        removeSelector: '[data-remove-benefit]',
        rowSelector: '.benefit-row',
    });

    wireRepeater({
        addSelector: '[data-add-rate]',
        containerSelector: '[data-rates]',
        templateSelector: '#rate-template',
        removeSelector: '[data-remove-rate]',
        rowSelector: '.rate-row',
    });

    document.querySelectorAll('[data-feedback-form]').forEach((feedbackForm) => {
        feedbackForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = event.submitter;
            if (!button || feedbackForm.dataset.saving === 'true') return;
            const buttons = [...feedbackForm.querySelectorAll('button[name="vote"]')];
            const status = feedbackForm.querySelector('.feedback-status');
            const body = new FormData(feedbackForm);
            body.set('vote', button.value);
            feedbackForm.dataset.saving = 'true';
            buttons.forEach((item) => item.disabled = true);
            status.textContent = 'A guardar…';
            try {
                const response = await fetch(feedbackForm.action, {
                    method: 'POST', body, credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.message || 'Não foi possível guardar. Tente novamente.');
                buttons.forEach((item) => item.setAttribute('aria-pressed', String(item === button)));
                status.textContent = result.message;
            } catch (error) {
                status.textContent = error.message || 'Não foi possível guardar. Tente novamente.';
            } finally {
                feedbackForm.dataset.saving = 'false';
                buttons.forEach((item) => item.disabled = false);
            }
        });
    });

    const form = document.querySelector('[data-intelligence-form]');
    if (!form) return;

    const serviceInputs = [...form.querySelectorAll('[data-service-toggle]')];
    const priorityField = form.querySelector('[data-priority-field]');
    const callScopeField = form.querySelector('[data-call-scope-field]');

    const updateServices = () => {
        const selected = serviceInputs.filter((input) => input.checked).map((input) => input.value);
        form.querySelectorAll('[data-service-panel]').forEach((panel) => {
            panel.hidden = !selected.includes(panel.dataset.servicePanel);
        });
        if (priorityField) priorityField.hidden = selected.length <= 1;
        if (callScopeField) callScopeField.hidden = !selected.includes('voice');

        const priority = form.querySelector('[name="priority"]');
        if (priority && priority.value !== 'balanced' && !selected.includes(priority.value)) priority.value = 'balanced';
    };
    serviceInputs.forEach((input) => input.addEventListener('change', updateServices));
    updateServices();

    const updateModes = () => {
        const dataMode = form.querySelector('[name="data_mode"]:checked')?.value;
        const voiceMode = form.querySelector('[name="voice_mode"]:checked')?.value;
        const smsMode = form.querySelector('[name="sms_mode"]:checked')?.value;
        const socialMode = form.querySelector('[name="social_mode"]:checked')?.value;

        const visibility = {
            data: dataMode === 'minimum',
            'voice-minutes': voiceMode === 'minutes',
            'voice-calls': voiceMode === 'calls',
            sms: smsMode === 'minimum',
            social: socialMode === 'minimum',
        };
        form.querySelectorAll('[data-mode-target]').forEach((target) => {
            target.hidden = !visibility[target.dataset.modeTarget];
        });
    };
    form.querySelectorAll('input[type="radio"]').forEach((input) => input.addEventListener('change', updateModes));
    updateModes();
})();

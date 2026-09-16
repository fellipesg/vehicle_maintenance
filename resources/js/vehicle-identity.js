function initVehicleIdentity(root = document) {
    root.querySelectorAll('[data-copy-chassis]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.chassis || '';
            if (!value) {
                return;
            }
            try {
                await navigator.clipboard.writeText(value);
                const original = button.textContent;
                button.textContent = 'Copiado!';
                window.setTimeout(() => {
                    button.textContent = original;
                }, 2000);
            } catch {
                // ignore
            }
        });
    });
}

export { initVehicleIdentity };

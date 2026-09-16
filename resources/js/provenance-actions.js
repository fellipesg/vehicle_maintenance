export function initProvenanceActions(root = document) {
    root.querySelectorAll('[data-copy-verification-code]').forEach((button) => {
        if (button.dataset.provenanceCopyBound === '1') {
            return;
        }

        button.dataset.provenanceCopyBound = '1';
        button.addEventListener('click', async () => {
            const code = button.dataset.code ?? '';

            try {
                await navigator.clipboard.writeText(code);
                const original = button.textContent;
                button.textContent = 'Código copiado';
                window.setTimeout(() => {
                    button.textContent = original;
                }, 2000);
            } catch {
                window.prompt('Copie o código:', code);
            }
        });
    });
}

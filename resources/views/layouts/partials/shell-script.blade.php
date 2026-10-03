{{--
    Comportamentos do shell: menus em HSOverlay (Preline) e avisos de sessão. Os plugins do Preline
    são inicializados em resources/js/app.js.
    Os overlays declaram data-shell-overlay-close-from="md|lg": acima desse breakpoint o menu vira
    barra/sidebar fixa, então um overlay aberto é fechado (senão o fundo escuro e o bloqueio de
    rolagem ficariam na tela).
--}}
<script>
    (() => {
        const breakpoints = { md: '(min-width: 48rem)', lg: '(min-width: 64rem)' };
        const overlays = Array.from(document.querySelectorAll('.hs-overlay[data-shell-overlay]'));

        const overlayInstance = (overlay) => (window.$hsOverlayCollection || [])
            .find(({ element }) => element.el === overlay)?.element ?? null;

        const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        const visibleFocusables = (overlay) => Array.from(overlay.querySelectorAll(focusableSelector))
            .filter((element) => !element.hidden && element.getClientRects().length > 0);

        const isSamePageAnchor = (link) => link.hash !== ''
            && link.origin === window.location.origin
            && link.pathname === window.location.pathname
            && link.search === window.location.search;

        overlays.forEach((overlay) => {
            // O Preline 4.2 trata Enter dentro de um .hs-overlay como "abrir o overlay" e chama
            // preventDefault: links e botões do menu não funcionariam pelo teclado, e a sidebar do
            // admin abriria como gaveta no desktop. Parar o Enter aqui mantém o comportamento nativo.
            // O HSOverlay só prende o Tab: Shift+Tab no primeiro item (ou no próprio painel, que recebe o
            // foco ao abrir) levaria o foco para trás do fundo escuro. Aqui ele volta para o último item.
            overlay.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && event.target.closest('a[href], button')) {
                    event.stopPropagation();

                    return;
                }

                if (event.key !== 'Tab' || !event.shiftKey || !overlay.classList.contains('open')) {
                    return;
                }

                const focusables = visibleFocusables(overlay);

                if (focusables.length > 0 && (document.activeElement === overlay || document.activeElement === focusables[0])) {
                    event.preventDefault();
                    focusables[focusables.length - 1].focus();
                }
            });

            // Âncora da própria página (ex.: #preco) não recarrega nada: fecha o menu para o conteúdo aparecer.
            overlay.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');

                if (link && isSamePageAnchor(link) && overlay.classList.contains('open')) {
                    overlayInstance(overlay)?.close();
                }
            });

            const closeFrom = window.matchMedia(breakpoints[overlay.dataset.shellOverlayCloseFrom] ?? breakpoints.lg);

            closeFrom.addEventListener('change', (event) => {
                if (event.matches && overlay.classList.contains('open')) {
                    overlayInstance(overlay)?.close(true);
                }
            });
        });

        document.addEventListener('click', (event) => {
            const dismissButton = event.target.closest('[data-flash-dismiss]');

            if (!dismissButton) {
                return;
            }

            dismissButton.closest('[data-flash]')?.remove();
            document.getElementById('conteudo')?.focus({ preventScroll: true });
        });
    })();
</script>

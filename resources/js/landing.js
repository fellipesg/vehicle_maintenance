/**
 * Landing (home.blade.php). Um momento de movimento por seção, todos só com
 * prefers-reduced-motion: no-preference; com redução de movimento a página fica parada e completa.
 *
 * - Hero: a entrada (.landing-intro) é só CSS; aqui fica o botão "Pausar animação", que para a faixa
 *   de capacidades e a flutuação do mock (WCAG 2.2.2).
 * - Como funciona: o trilho dos passos ([data-landing-rail]) se desenha ao entrar na tela.
 * - Procedência e Para quem: revelação curta ([data-landing-reveal]).
 * - Produto: as abas são de resources/js/ui/tabs.js; a troca de painel anima por @starting-style.
 */
export function initLanding() {
    const root = document.querySelector('[data-landing-page]');

    if (!root || root.hasAttribute('data-landing-ready')) {
        return;
    }

    root.setAttribute('data-landing-ready', '');

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canObserve = !reduceMotion && 'IntersectionObserver' in window;

    initLoopToggle(root, reduceMotion);
    initReveals(root, canObserve);
    initRails(root, canObserve);
}

/**
 * O elemento já está (quase) à vista ao carregar: não vale esconder para animar depois.
 *
 * @param {Element} element
 * @param {number} ratio fração da altura da janela
 */
function isAlreadyInView(element, ratio) {
    return element.getBoundingClientRect().top < window.innerHeight * ratio;
}

/**
 * @param {Element} root
 * @param {boolean} canObserve
 */
function initReveals(root, canObserve) {
    const reveals = root.querySelectorAll('[data-landing-reveal]');

    if (!canObserve) {
        reveals.forEach((element) => element.classList.add('is-visible'));

        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.remove('is-pending');
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.12, rootMargin: '0px 0px -8% 0px' },
    );

    reveals.forEach((element) => {
        if (isAlreadyInView(element, 0.88)) {
            element.classList.add('is-visible');

            return;
        }

        element.classList.add('is-pending');
        observer.observe(element);
    });
}

/**
 * Trilho de "Como funciona" (x-landing.step): abaixo da dobra fica data-landing-rail="pending"
 * (conectores encolhidos, números apagados) e vira "drawn" ao entrar na tela; os atrasos em CSS
 * acendem um passo depois do outro. Sem observador ou já visível, o trilho fica desenhado.
 *
 * @param {Element} root
 * @param {boolean} canObserve
 */
function initRails(root, canObserve) {
    const rails = root.querySelectorAll('[data-landing-rail]');

    if (!canObserve) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.setAttribute('data-landing-rail', 'drawn');
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.35, rootMargin: '0px 0px -10% 0px' },
    );

    rails.forEach((rail) => {
        if (isAlreadyInView(rail, 0.8)) {
            return;
        }

        rail.setAttribute('data-landing-rail', 'pending');
        observer.observe(rail);
    });
}

/**
 * Botão "Pausar animação" do hero (aria-pressed). Para a faixa de capacidades
 * ([data-landing-marquee-track]) e os laços decorativos ([data-landing-loop], a flutuação do mock).
 * Retomar tira o estilo inline e a faixa volta a andar na hora, mesmo com o botão focado ou sob o
 * ponteiro: o CSS só pausa no hover e no foco da própria faixa, da qual o botão não faz parte. Com
 * prefers-reduced-motion o CSS já desliga tudo e o botão continua escondido.
 *
 * @param {Element} root
 * @param {boolean} reduceMotion
 */
function initLoopToggle(root, reduceMotion) {
    const toggle = root.querySelector('[data-landing-marquee-toggle]');
    const loops = root.querySelectorAll('[data-landing-marquee-track], [data-landing-loop]');

    if (!toggle || loops.length === 0 || reduceMotion) {
        return;
    }

    const pauseIcon = toggle.querySelector('[data-landing-marquee-icon="pause"]');
    const playIcon = toggle.querySelector('[data-landing-marquee-icon="play"]');

    const setPaused = (paused) => {
        toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
        pauseIcon?.toggleAttribute('hidden', paused);
        playIcon?.toggleAttribute('hidden', !paused);

        loops.forEach((element) => {
            if (paused) {
                element.style.animationPlayState = 'paused';
            } else {
                element.style.removeProperty('animation-play-state');
            }
        });
    };

    toggle.addEventListener('click', () => {
        setPaused(toggle.getAttribute('aria-pressed') !== 'true');
    });

    toggle.hidden = false;
}

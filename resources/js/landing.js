export function initLanding() {
    const root = document.querySelector('[data-landing-page]');

    if (!root) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const reveals = root.querySelectorAll('[data-landing-reveal]');

    if (!reduceMotion && 'IntersectionObserver' in window) {
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

        reveals.forEach((el) => {
            const rect = el.getBoundingClientRect();

            if (rect.top < window.innerHeight * 0.88) {
                el.classList.add('is-visible');

                return;
            }

            el.classList.add('is-pending');
            observer.observe(el);
        });
    } else {
        reveals.forEach((el) => el.classList.add('is-visible'));
    }
}

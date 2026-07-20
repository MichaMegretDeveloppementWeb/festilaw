// Menu mobile de l'en-tete public : bascule en vanilla JS (aucune dependance).
function initHeaderMenu() {
    const burger = document.querySelector('.site-header__burger');
    const menu = document.querySelector('.site-header__mobile');

    if (!burger || !menu) {
        return;
    }

    const setOpen = (open) => {
        menu.classList.toggle('is-open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    burger.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(!menu.classList.contains('is-open'));
    });

    document.addEventListener('click', (event) => {
        if (menu.classList.contains('is-open') && !menu.contains(event.target) && !burger.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });
}

// En-tete collant : reduit le logo (classe .is-scrolled) des que la page quitte le haut.
//
// Deux seuils avec une zone morte (hysteresis) : la reduction change la hauteur du header, le
// navigateur ajuste scrollY (scroll anchoring) et, avec un seuil unique, ce decalage le refranchit
// et fait osciller le header. La zone morte, plus large que la variation de hauteur (~80px), l'evite.
function initHeaderScroll() {
    const header = document.querySelector('.site-header');

    if (!header) {
        return;
    }

    const SHRINK_AT = 160;
    const GROW_AT = 20;
    let ticking = false;

    const update = () => {
        const y = window.scrollY;

        if (y > SHRINK_AT) {
            header.classList.add('is-scrolled');
        } else if (y < GROW_AT) {
            header.classList.remove('is-scrolled');
        }

        ticking = false;
    };

    const onScroll = () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(update);
        }
    };

    update();
    window.addEventListener('scroll', onScroll, { passive: true });
}

function init() {
    initHeaderMenu();
    initHeaderScroll();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

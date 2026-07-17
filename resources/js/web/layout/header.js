// Menu mobile de l'en-tete public : bascule en vanilla JS (aucune dependance). Ouvre/ferme le panneau,
// tient a jour aria-expanded, et ferme au clic exterieur, sur Echap, ou a la selection d'un lien.
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

    // Clic en dehors du menu et du burger : on ferme.
    document.addEventListener('click', (event) => {
        if (menu.classList.contains('is-open') && !menu.contains(event.target) && !burger.contains(event.target)) {
            setOpen(false);
        }
    });

    // Touche Echap : on ferme.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    // Selection d'un lien : on ferme (utile quand la cible est une ancre de la meme page).
    menu.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });
}

// En-tete collant : reduit le logo (classe .is-scrolled) des que la page quitte le haut.
//
// Hysteresis (deux seuils avec une zone morte) : quand le header se reduit, sa hauteur change, la page
// se reagence et le navigateur ajuste scrollY (scroll anchoring). Avec un seuil unique, ce decalage
// refranchit le seuil et fait osciller le header. Une zone morte plus large que la variation de hauteur
// (~80px) empeche ce va-et-vient : on reduit au-dela de SHRINK_AT, on agrandit seulement en deca de
// GROW_AT, et entre les deux on garde l'etat courant.
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

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHeaderMenu);
} else {
    initHeaderMenu();
}

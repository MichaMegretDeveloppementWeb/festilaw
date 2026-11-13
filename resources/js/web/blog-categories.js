// Menu des categories du blog : une seule ligne qui defile. Une fleche et un fondu n'apparaissent que du
// cote ou il reste des categories ; un clic sur une fleche decale la ligne de deux categories.
const STEP = 2;
// Place prise par une fleche au bord de la ligne : la categorie visee ne doit pas finir dessous.
const ARROW_SPACE = 48;

document.querySelectorAll('[data-blog-cats]').forEach((nav) => {
    const track = nav.querySelector('[data-blog-cats-track]');
    const previous = nav.querySelector('[data-blog-cats-prev]');
    const next = nav.querySelector('[data-blog-cats-next]');

    if (!track || !previous || !next) {
        return;
    }

    const links = [...track.children];
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const update = () => {
        const maximum = track.scrollWidth - track.clientWidth;
        const moreBefore = track.scrollLeft > 1;
        const moreAfter = track.scrollLeft < maximum - 1;

        previous.hidden = !moreBefore;
        next.hidden = !moreAfter;
        nav.classList.toggle('has-more-before', moreBefore);
        nav.classList.toggle('has-more-after', moreAfter);
    };

    // Position de defilement qui place une categorie au bord gauche, a cote de la fleche s'il y en a une.
    const positionOf = (index) => (index <= 0 ? 0 : links[index].offsetLeft - ARROW_SPACE);

    // Premiere categorie entierement visible a gauche.
    const firstVisible = () => {
        const edge = track.scrollLeft + (track.scrollLeft > 1 ? ARROW_SPACE : 0);
        const index = links.findIndex((link) => link.offsetLeft >= edge - 1);

        return index === -1 ? links.length - 1 : index;
    };

    const moveTo = (index) => {
        track.scrollTo({
            left: positionOf(Math.max(0, Math.min(index, links.length - 1))),
            behavior: reducedMotion.matches ? 'auto' : 'smooth',
        });
    };

    previous.addEventListener('click', () => moveTo(firstVisible() - STEP));
    next.addEventListener('click', () => moveTo(firstVisible() + STEP));
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);

    // La categorie de la page courante est amenee a l'ecran, sans animation.
    const current = track.querySelector('.is-active');

    if (current && current.offsetLeft + current.offsetWidth > track.clientWidth) {
        track.scrollLeft = positionOf(links.indexOf(current));
    }

    update();
});

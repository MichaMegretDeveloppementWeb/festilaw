// Adresse (slug) d'un formulaire du back-office qui suit le titre ou le nom tant qu'on ne l'a pas
// modifiee a la main. Partage par le formulaire d'article et celui de categorie.
// Le composant Livewire porte `texts[langue].slug`, `slugEdited[langue]` et la methode `suggestSlug(langue)`,
// qui rend l'adresse calculee par le serveur (meme generateur et meme unicite qu'a l'enregistrement).

export const slugField = {
    // Appele a la sortie du champ titre ou nom.
    async suggestSlug(locale) {
        if (this.$wire.slugEdited[locale]) {
            return;
        }

        const slug = await this.$wire.suggestSlug(locale);

        // La reponse arrive apres coup : entre-temps, l'adresse a pu etre saisie a la main.
        if (! this.$wire.slugEdited[locale]) {
            this.$wire.texts[locale].slug = slug ?? '';
        }
    },

    // « Recreer a partir du titre » : l'adresse suit de nouveau le titre.
    resetSlug(locale) {
        this.$wire.slugEdited[locale] = false;
        this.dirty = true;

        return this.suggestSlug(locale);
    },
};

// Enregistre un composant Alpine, que le script arrive avant ou apres le demarrage d'Alpine.
export const registerAlpine = (name, factory) => {
    const register = () => window.Alpine.data(name, factory);

    if (window.Alpine) {
        register();
    } else {
        document.addEventListener('alpine:init', register);
    }
};

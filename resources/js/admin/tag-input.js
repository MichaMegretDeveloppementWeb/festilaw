// Champ a pastilles du back-office (<x-admin.tag-input>) : une liste de valeurs courtes, saisies une a une.
// `tags` est lie a une propriete du composant Livewire par `wire:model` (x-modelable) : la liste est toujours
// remplacee par une nouvelle, jamais modifiee sur place, pour que la liaison voie le changement.

import { registerAlpine } from './slug-field';

const adminTagInput = ({ max, maxLength }) => ({
    tags: [],
    draft: '',

    // Ajoute ce qui est tape (ou un texte colle, une valeur par ligne). Ni vide, ni doublon, ni au-dela du nombre permis.
    add(text = this.draft) {
        const known = new Set((this.tags ?? []).map((tag) => tag.toLowerCase()));
        const added = [];

        text.split(/[\r\n]+/).forEach((line) => {
            const value = line.replace(/\s+/g, ' ').trim().slice(0, maxLength);

            if (value !== '' && ! known.has(value.toLowerCase())) {
                known.add(value.toLowerCase());
                added.push(value);
            }
        });

        this.draft = '';

        if (added.length > 0) {
            this.tags = [...(this.tags ?? []), ...added].slice(0, max);
        }
    },

    remove(index) {
        this.tags = this.tags.filter((tag, position) => position !== index);
    },

    removeLastWhenEmpty() {
        if (this.draft === '' && this.tags.length > 0) {
            this.tags = this.tags.slice(0, -1);
        }
    },

    // Un texte colle sur plusieurs lignes devient plusieurs pastilles ; sur une seule ligne, il reste a valider.
    paste(event) {
        const text = event.clipboardData?.getData('text') ?? '';

        if (/[\r\n]/.test(text)) {
            event.preventDefault();
            this.add(this.draft + text);
        }
    },
});

registerAlpine('adminTagInput', adminTagInput);

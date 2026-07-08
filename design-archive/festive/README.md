# Design « Festive » · archive

Instantané **figé** du premier design du site Festilaw : direction **ludique et colorée**
(crème/corail/cramoisi/navy/jaune, typos Schibsted Grotesk · Hanken Grotesk · Newsreader,
gribouillis dessinés main, taches de couleur, rotations, surligneur, animations `floaty`).

- **Gelé le :** 2026-07-08.
- **Pourquoi :** la cliente a décidé de repartir sur un design plus sobre, jugé plus adapté
  au sujet (réglementation). Ce dossier conserve la version « Festive » **validée** au cas où
  on voudrait y revenir, ou la réutiliser pour un autre projet.
- **Statut :** copie inerte. **Rien dans le build ne référence ce dossier**, il n'influence donc
  pas le design courant. Il n'est pas maintenu : il reflète l'état du site à la date ci-dessus.

## Contenu

| Dans l'archive | Correspond à (à la racine du projet) |
|---|---|
| `css/web.css` | `resources/css/web.css` |
| `css/web/` | `resources/css/web/` (base, layout, home, contact) |
| `views/layouts/web.blade.php` | `resources/views/layouts/web.blade.php` |
| `views/web/home/` | `resources/views/web/home/` (index + partials) |
| `views/web/contact/index.blade.php` | `resources/views/web/contact/index.blade.php` |
| `views/components/layout/web/` | `resources/views/components/layout/web/` |
| `views/livewire/web/contact/contact-form.blade.php` | `resources/views/livewire/web/contact/contact-form.blade.php` |
| `vite.config.js` | `vite.config.js` |
| `docs/` | copie de `project-management/festilaw/03-design/` (direction artistique, tokens, source Claude Design) |

> Le JS du quiz (Alpine) vit dans `views/web/home/partials/quiz.blade.php` (bloc `@push('scripts')`),
> il est donc inclus. Les polices (Bunny Fonts) sont chargées dans `views/layouts/web.blade.php`.

## Restaurer le design « Festive »

Ce n'est **pas** automatique (volontairement, pour éviter tout écrasement accidentel).
Pour rallumer cette version :

1. Recopier chaque élément de l'archive vers sa destination d'origine (voir le tableau ci-dessus),
   en **écrasant** les fichiers du design courant.
2. Restaurer `vite.config.js` (entrées CSS : `resources/css/web.css`,
   `resources/css/web/home/index.css`, `resources/css/web/contact/index.css`).
3. Reconstruire et vider les caches :
   ```
   npm run build
   php artisan optimize:clear
   ```

## Ce qui n'est PAS ici (et n'a pas besoin de l'être)

Structure et logique communes aux deux designs, donc conservées telles quelles dans le projet :
routes, contrôleurs, composant Livewire `ContactForm` (la classe), middleware i18n, modèle `Submission`.
Seul **l'habillage** (CSS + décor dans les vues) diffère d'un design à l'autre.

## Autre filet de sécurité

Le projet est aussi sous **Git** en local : l'historique constitue une seconde voie de restauration.

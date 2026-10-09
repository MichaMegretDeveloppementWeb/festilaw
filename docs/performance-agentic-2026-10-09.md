# Performance et navigation agentique — diagnostic, plan et résultat

État du 9 octobre 2026 : corrections implémentées et vérifiées **localement**, prêtes pour le déploiement Hostinger par Micha. Ce rapport complète et remplace les résultats « après » de la [première passe](technical-corrections-2026-10-09.md). Les corrections www, footer et JSON-LD de cette première passe restent incluses.

## Diagnostic en production

Le site contrôlé est bien **festilaw.com**, confirmé par Micha. Chrome/Lighthouse 13.5.0 mesure l'accueil à **80/100 en mobile, LCP 5,264 s**, et **99/100 en desktop, LCP 1,030 s**. Le résultat utilisateur à 51/100 n'a pas été reproduit dans ce profil vierge. Les conditions de mesure, la version de Chrome, les extensions, les cookies de langue et la charge machine/serveur peuvent varier ; le 51 n'est donc pas présenté comme un résultat corrigé et vérifié en production.

La production sert toujours le logo PNG de 390 941 octets et l'ancienne photo WebP de 287 796 octets : la première passe locale n'est pas encore déployée. L'accueil transfère au total **919 511 octets** dans le nouvel audit en ligne.

Le rapport révèle aussi :

- environ **94,5 Ko transférés pour Livewire**, chargé même sans formulaire, principalement pour le quiz Alpine ;
- la photo principale découverte depuis le CSS ;
- des polices utiles en haut de page découvertes trop tard, avec déplacement des onglets ;
- un `role="img"` invalide sur une balise `section`, qui fait échouer l'audit de structure accessible ;
- un cache de sept jours sur les fichiers statiques, y compris les bundles dont le nom contient déjà une empreinte.

Les deux contrôles agentiques applicables à ce site dans cette version sont **la structure accessible** et **la stabilité visuelle (CLS)**. Le nouvel audit production donne **1/2**, contre le 0/2 observé par Micha. Cette catégorie est expérimentale et son périmètre dépend de la version ; elle ne mesure pas une garantie universelle de navigation par tous les agents. [Documentation Chrome](https://developer.chrome.com/docs/lighthouse/agentic-browsing/scoring).

## Plan exécuté et raisons des choix

| Action | Pourquoi et résultat concret |
| --- | --- |
| Conserver le logo WebP 272 px de la première passe | 15 420 octets, repli PNG conservé ; même taille affichée. |
| Ajouter la photo AVIF, conserver le repli WebP | **124 141 octets**, mêmes dimensions 1448 × 1086 et même parallax. Le navigateur choisit le format compatible via `image-set`. Seul l'AVIF est préchargé pour éviter de télécharger les deux formats. [Référence CSS](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/Values/image/image-set). |
| Remplacer le quiz et les onglets Alpine par du JavaScript natif | Leurs interactions ne nécessitent pas Livewire. Bundles quiz, onglets et navigation : **moins de 2 Ko gzip au total**. Les trois questions, règles de résultat, traductions et POST anonyme sont conservés. |
| Charger Livewire seulement quand un composant le nécessite | Utilisation de l'injection automatique de Livewire, conservée sur tous les formulaires. La page de lien expiré renvoie 404 : elle garde explicitement ses assets, car l'injection automatique ne couvre que les réponses 200. [Documentation Livewire](https://livewire.laravel.com/docs/4.x/installation). |
| Corriger la sémantique de la photo et des onglets | La photo est un `div` avec rôle image et description. Onglets nommés, reliés à leurs panneaux, état sélectionné explicite ; navigation par flèches, Début et Fin. Le quiz place le focus sur la nouvelle question ou le résultat. |
| Prioriser Inter 600 à la place du preload Satisfy | Les boutons et onglets en ont besoin immédiatement ; Satisfy reste chargée normalement pour les textes plus bas. Les décalages des onglets disparaissent dans l'audit final (CLS 0). |
| Cache long uniquement pour les bundles CSS/JS avec empreinte | `public, max-age=31536000, immutable` pour ces fichiers versionnés. Un nouveau build change leur nom. Aucun cache public ajouté aux pages HTML, formulaires ou documents clients. À confirmer sur LiteSpeed après déploiement. |

**Choix confirmés par Micha : couleurs actuelles conservées.** Les contrastes existants empêchent donc le 100/100 en accessibilité. Les `noindex` des parcours sont également conservés : leur rôle est de protéger l'indexation, pas d'obtenir un meilleur score SEO.

Aucune dépendance ajoutée, aucun changement de contrôleur, route, configuration métier, paiement, signature ou jeton client. Les images ont été encodées avec l'outil Sharp déjà disponible ; aucun paquet n'a été installé.

## Résultats après, en local

Même Lighthouse 13.5.0 et Chromium 153, profil mobile 412 × 823, DPR 1,75, réseau simulé 1 638,4 kbit/s, RTT 150 ms, CPU ×4, navigateur vierge/cache froid. Les autres catégories et la navigation agentique sont incluses dans les rapports complets.

| Page / profil | Performance | LCP | Agentique | Accessibilité | Bonnes pratiques | SEO |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Accueil mobile, médiane de 3 passages | **99** | **2,199 s** | **2/2** | 96 | 100 | 100 |
| Tarifs mobile | **100** | **1,519 s** | **2/2** | 95 | 100 | 100 |
| Souscription Creator mobile | **100** | **1,516 s** | **2/2** | 92 | 100 | 69¹ |
| Comprendre GPSR mobile | **100** | **1,521 s** | **2/2** | 97 | 100 | 100 |
| Accueil desktop | **100** | **0,461 s** | **2/2** | 96 | 100 | 100 |

¹ La souscription est volontairement non indexable ; aucune suppression du `noindex` pour améliorer artificiellement le score.

Accueil mobile, passages individuels : **98 / 2,207 s**, **99 / 2,199 s**, **99 / 2,124 s**. CLS **0,00073** ; tarifs, souscription et GPSR : **0**. Le transfert de l'accueil est d'environ **286 823 octets**, soit **68,8 % de moins que la version en production**. Le même environnement local passe de **96 / 2,727 s** avant cette seconde passe à la médiane **99 / 2,199 s** après.

Ces chiffres ne sont **pas des mesures après déploiement Hostinger**. Ils établissent le gain du code et des assets ; le TTFB, la compression et le cache de production doivent être revérifiés après publication.

## Vérifications réalisées

- **512 tests Pest réussis, 1 917 assertions**, suite complète ; Pint, build Vite et `git diff --check` réussis.
- **9 tests JavaScript natifs Node** : huit combinaisons de réponses et absence de résultat pour des réponses incomplètes ou invalides.
- Dans Chrome local : trois étapes du quiz, résultat, redémarrage, traductions EN/FR/ES, sélection et navigation clavier des onglets, ouverture du menu mobile et fermeture avec Échap.
- Le POST du quiz conserve exactement ses trois champs et la protection CSRF. Lors du test navigateur, l'endpoint statistique a été temporairement bloqué dans l'onglet de test : résultat toujours visible et payload vérifié, sans créer de fausse statistique. Blocage ensuite retiré.
- Présence de Livewire vérifiée sur contact, STARTER, PRO, SCALE, accès au projet et page de lien expiré. Les tests des paiements, signatures et liens personnels passent dans la suite complète.
- Captures Lighthouse avant/après comparées : mêmes dimensions, cadrage et couleurs. AVIF contrôlé visuellement à la taille affichée.

Rapports HTML/JSON complets et captures : `.codex/performance-round2/`. Les fichiers `final-*` sont les mesures finales, `production-*-before` les constats en ligne et `local-before-*` la référence locale de début de seconde passe. `summary.json` synthétise toutes les valeurs. Les fichiers intermédiaires restent identifiés séparément. Ces artefacts sont locaux et non versionnés.

## Fichiers ajoutés ou modifiés dans cette seconde passe

- `public/.htaccess` : cache des bundles et type MIME AVIF, en plus de la redirection www.
- `public/images/home-transport-optimized.avif` et `resources/css/web/home/photo.css`.
- `resources/views/layouts/web.blade.php` : chargement Livewire et priorités de polices.
- `resources/views/web/home/index.blade.php` : preload AVIF et rôle image valide.
- `resources/views/web/sections/quiz.blade.php`, `resources/js/web/quiz.js`, `resources/js/web/quiz-state.js`.
- `resources/views/web/understand-gpsr/index.blade.php`, `resources/js/web/tabs.js`.
- `resources/views/web/dossier-link-invalid.blade.php` : conservation explicite des assets Livewire sur la réponse 404.
- `resources/css/web/base/reset.css` : respect de l'attribut natif `hidden` pour les composants flex/grid.
- `vite.config.js`, `public/build/manifest.json` et bundles reconstruits.
- `tests/Feature/Web/PublicAssetsTest.php`, `tests/JavaScript/quiz.test.js` et ce rapport.

## À faire sur Hostinger

1. Déployer l'ensemble du lot, y compris les fichiers de la première passe, le fichier `.htaccess` du public réellement servi, **l'AVIF et les replis**, le manifeste et les nouveaux bundles compilés. Aucune installation npm ou nouvelle dépendance nécessaire sur le serveur.
2. Exécuter le déploiement habituel et régénérer les vues/caches Laravel avec PHP 8.5. Vérifier `APP_URL=https://festilaw.com` et les URL externes comme décrit dans le premier rapport.
3. Purger l'éventuel cache CDN/LiteSpeed des pages publiques. Vérifier le 301 www et l'absence de boucle. Contrôler que l'AVIF renvoie `Content-Type: image/avif` et qu'un fichier de `public/build/assets/` reçoit bien le cache long prévu.
4. Ouvrir l'accueil, faire le quiz, tester les onglets et ouvrir le formulaire de souscription ; vérifier aussi l'accès à un lien personnel de test existant.
5. Relancer Lighthouse **mobile, cache froid**, trois fois sur l'accueil, puis sur `/pricing`, `/get-started/starter` et `/understand-gpsr`. Activer la catégorie navigation agentique. Conserver les rapports : les résultats de production, et non les mesures locales, confirmeront la cible >95 et 2/2 sur Hostinger.

Si la production reste nettement en dessous de la version locale après ce déploiement, comparer d'abord les URL des bundles/images réellement servis, leur taille transférée et le délai de réponse HTML ; cela permet de distinguer des assets anciens encore en cache d'un délai côté hébergement.

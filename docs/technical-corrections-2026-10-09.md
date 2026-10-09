# Corrections domaine et performances — 9 octobre 2026

> Première passe conservée comme historique. La [seconde passe performance et navigation agentique](performance-agentic-2026-10-09.md) contient les résultats finaux et les fichiers supplémentaires à déployer.

Modifications locales prêtes à déployer par Micha. Aucun accès SSH de production utilisé.
Aucune dépendance ajoutée. Aucun changement du code des parcours, du paiement ou de la signature.

## Changements

- `public/.htaccess` : redirection 301 de `www.festilaw.com` vers `https://festilaw.com`, avant les règles Laravel et les fichiers statiques. Chemin et paramètres conservés. Le domaine nu et le domaine local ne sont pas redirigés par cette règle. Voir [la documentation Apache](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html).
- Logo public : source PNG 1024 × 1024, 390 941 octets → WebP 272 × 272, 15 420 octets (−96,1 %). Repli PNG 68 278 octets. Le maximum CSS est 128 px avec un agrandissement de 1,06 ; 272 px couvre donc deux fois la taille affichée. Pas de source vectorielle trouvée dans le projet.
- Les deux images de logo des pages publiques gardent leurs dimensions HTML et CSS. Le logo de la section « Who we are » utilise `loading="lazy"` et `decoding="async"` ; le logo de navigation reste chargé immédiatement.
- Photo de l'accueil : WebP réencodé à qualité 75, de 287 796 à 223 266 octets (−22,4 %), dimensions 1448 × 1086 conservées. Le fond fixe couvre le viewport : le réduire à la seule largeur du téléphone dégraderait sa résolution. Cadrage, hauteur CSS réservée et effet parallax conservés. Comparaison visuelle effectuée.
- Cette photo est l'élément LCP mobile (partiellement visible dès le premier écran). Un preload d'image `fetchpriority="high"` la découvre depuis le HTML. Elle ne doit donc pas être chargée en lazy. Comme c'est un fond CSS, sa place est réservée par la hauteur de la section, sans attributs HTML width/height applicables.
- Aucun autre bitmap dans les pages publiques : les autres illustrations sont des SVG intégrés. Les images originales restent disponibles aux e-mails et aux PDF ; leur code est inchangé.
- Lien d'administration et styles associés supprimés du footer. `/admin/login` reste accessible.
- JSON-LD `Organization / LegalService` complété : Festilaw B.V., KvK 77058720, TVA **NL860886761B01**, e-mail, URL, logo et adresse. Adresse trouvée dans les mentions légales et la politique de confidentialité : **Spoorstraat 30A, 6511 AN Nijmegen, NL**. La TVA complète reprend le pied de page existant, sans la tronquer au préfixe fourni dans la demande.

## Mesures Lighthouse mobile

Lighthouse 13.5.0, Chromium 153, profil mobile par défaut (412 × 823, DPR 1,75), réseau simulé (1 638,4 kbit/s, RTT 150 ms, CPU ×4), cache froid, même machine et mêmes paramètres. Une mesure par page et par état, donc valeurs indicatives soumises à variation. Pas d'avertissement Lighthouse. Les scores ci-dessous sont ceux de la catégorie Performance.

| Page | Production avant (score / LCP) | Local avant (score / LCP) | Local après (score / LCP) |
| --- | --- | --- | --- |
| `/` | 80 / 5,29 s | 82 / 4,82 s | 96 / 2,72 s |
| `/pricing` | 91 / 3,47 s | 92 / 3,31 s | 100 / 1,37 s |
| `/get-started/starter` | 89 / 3,78 s | 92 / 3,32 s | 100 / 1,52 s |

**La cible LCP < 2,5 s n'est pas encore atteinte sur l'accueil local.** Les mesures locales ne prédisent pas la latence Hostinger. Aucun « après production » n'est annoncé : le déploiement reste à faire. Pas de modification supplémentaire du rendu ou des parcours pour forcer le score.

CLS après : accueil 0,00126 ; tarifs 0,00017 ; souscription 0. Captures Lighthouse avant/après comparées : même mise en page, mêmes dimensions et cadrage.

Rapports JSON complets, captures et journal Pest : `.codex/performance/` (artefacts locaux non versionnés). Les rapports `production-before-*`, `local-before-*` et `local-after-*` identifient explicitement l'environnement ; `summary.json` contient les réglages et valeurs brutes.

## Validation

- Suite Pest complète : **496 tests réussis, 1 873 assertions**, 34,01 s. Pint et `git diff --check` réussis. `npm run build` réussi ; assets compilés inclus.
- JSON-LD rendu localement, domaine remplacé par le domaine de production uniquement pour la soumission du code public à [validator.schema.org](https://validator.schema.org/) : **0 erreur, 0 avertissement**, deux éléments reconnus (`Organization / LegalService`, `WebSite`).
- Nouveaux tests : accès des liens personnels STARTER, espace client et SCALE après changement du seul hôte ; retours de paiement signés conservés et falsification toujours refusée ; canonical/sitemap/JSON-LD sur le domaine nu ; accès admin direct.
- Les tests Pest ne peuvent pas exécuter `.htaccess` : le serveur local est Nginx/Herd. Les contrôles HTTP de la règle restent à effectuer sur LiteSpeed après déploiement.

## Domaine : constat et déploiement Hostinger

Avant correction, `https://www.festilaw.com/` répond **200**, ce qui confirme le doublon. Le projet fournit déjà `APP_URL=https://festilaw.com` dans `.env.example` et `docs/go-live.md`. Le `.env` local garde volontairement `https://festilaw.test`. La valeur effective du `.env` et du cache de configuration de production n'a pas pu être lue.

Les canonical, Open Graph, sitemap, liens d'e-mails, retours Stripe et URL du webhook SignWell utilisent les helpers Laravel `url`, `asset` et `route`. En HTTP ils reprennent le domaine de la requête ; en CLI/queue ils dépendent d'APP_URL. La redirection serveur et APP_URL doivent donc être déployés ensemble. Aucune redirection ajoutée dans Laravel, aucun changement de jeton, de secret ou de signature.

1. Déployer les fichiers, **y compris `public/.htaccess`, les trois nouvelles images et `public/build/` compilé**. Le dossier public réellement servi par Hostinger doit contenir ce `.htaccess`. Conserver le certificat HTTPS et le rattachement DNS de `www` pour que la redirection HTTPS fonctionne.
2. Vérifier `APP_URL=https://festilaw.com` dans le `.env` de production ; ASSET_URL, si défini, ne doit pas réintroduire www. Puis régénérer les caches avec PHP 8.5 (le déploiement habituel le fait déjà) :

   ```bash
   /opt/alt/php85/usr/bin/php artisan config:cache
   /opt/alt/php85/usr/bin/php artisan view:clear
   /opt/alt/php85/usr/bin/php artisan view:cache
   /opt/alt/php85/usr/bin/php artisan tinker --execute='dump(config("app.url"));'
   ```

3. Dans les configurations existantes Stripe/SignWell, vérifier que les endpoints entrants pointent directement vers `https://festilaw.com/webhooks/payment/stripe` et `https://festilaw.com/webhooks/signature`. Les URL enregistrées chez ces prestataires ne sont pas consultables depuis ce dépôt. Une redirection 301 ne doit pas servir d'intermédiaire à leurs POST. Ne pas changer les secrets ni recréer les endpoints.
4. Aucun réglage hPanel supplémentaire requis si ce `.htaccess` est bien pris en compte. Si une redirection hPanel inverse « domaine nu → www » existe, la retirer pour éviter une boucle. Purger un éventuel cache CDN/LiteSpeed des pages publiques après déploiement.
5. Contrôler les redirections et les paramètres :

   ```bash
   curl -I 'https://www.festilaw.com/'
   curl -I 'https://www.festilaw.com/get-started/starter?source=audit&value=a%2Bb'
   curl -IL --max-redirs 5 'https://www.festilaw.com/get-started/starter?source=audit&value=a%2Bb'
   curl -I 'https://festilaw.com/'
   ```

   Attendu : premier saut **301** vers le même chemin/paramètres sur `https://festilaw.com`, puis **200**, sans boucle ; domaine nu directement **200**. Vérifier aussi `/sitemap.xml` et le canonical de `/pricing`.
6. Ouvrir un lien personnel de test encore valide déjà envoyé avant le déploiement, avec www s'il en comporte un. Sa validité initiale et sa date d'expiration restent inchangées. Les jetons et signatures relatives sont couverts automatiquement ; aucun dossier réel n'a été ouvert pendant ces travaux.
7. Relancer Lighthouse mobile sur les trois URL publiques pour obtenir les vrais résultats après production. Revalider l'URL publique avec schema.org. Si l'accueil reste au-dessus de 2,5 s, examiner le TTFB et la livraison des fichiers statiques Hostinger à partir de ce nouveau rapport.

## Fichiers livrés

- Serveur : `public/.htaccess`.
- Images : `public/images/logo-festilaw-272.webp`, `public/images/logo-festilaw-272.png`, `public/images/home-transport-optimized.webp`.
- Blade : `resources/views/components/layout/web/{header,footer}.blade.php`, `resources/views/components/seo/json-ld.blade.php`, `resources/views/web/home/index.blade.php`, `resources/views/web/sections/who-we-are.blade.php`.
- CSS : `resources/css/web/home/photo.css`, `resources/css/web/layout/{header,footer}.css`, `resources/css/web/sections/who-we-are.css`.
- Build : `public/build/manifest.json` et les trois feuilles CSS avec empreinte correspondantes (accueil, à propos, styles globaux).
- Tests : `tests/Feature/Web/CanonicalDomainTest.php`, `tests/Feature/Web/PublicSiteTest.php`.
- Ce compte rendu : `docs/technical-corrections-2026-10-09.md`.

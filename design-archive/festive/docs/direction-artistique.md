# Direction artistique

> Direction visuelle du site, telle qu'établie par le design **Festilaw Home** (Claude Design) et extraite dans le code. Référence : `source-festilaw-home.dc.html`, tokens : `design-tokens.md`.

## Intention

Un site **vivant, coloré et humain**, volontairement pas classique ni corporate froid. Il dédramatise un sujet réglementaire lourd par une esthétique chaleureuse (crème + corail), des touches ludiques (formes dessinées à la main, doodles flottants, cartes inclinées) et une hiérarchie typographique forte. Cohérent avec la volonté cliente (couleurs vives, aplats par section) et le positionnement *from entrepreneurs to entrepreneurs*.

## Univers visuel

- **Registre** : commerce international / transport maritime (photos de porte-conteneurs, vagues stylisées).
- **Aplats de couleur par section** : alternance crème / navy / blanc / dégradé corail (quiz), chaque section a une identité chromatique.
- **Motifs signature :**
  - **Formes dessinées à la main** (filtre SVG `handdrawn`) sur les icônes des piliers.
  - **Cartes inclinées** (`rotate`) avec **ombres portées pleines** décalées (ex. `13px 15px 0`), effet sticker.
  - **Blobs organiques** (border-radius asymétriques) derrière le visuel hero.
  - **Doodles flottants** animés (étoiles, vaguelettes, éclairs) avec `floaty` / `floaty2`.
  - **Vagues** en séparateur de sections (SVG) et « mer » en bas du quiz.
  - **Surligneurs** (`.mk`) façon marqueur sur les mots-clés des titres.

## Typographie

- **Schibsted Grotesk** (800-900) pour les grands titres et chiffres : impact, caractère.
- **Hanken Grotesk** pour le corps : lisibilité, neutralité chaleureuse.
- **Newsreader** (italique) pour les moments éditoriaux (manifeste « Who we are », témoignage) : humanité, respiration.

## Couleur

Navy `#0B1E45` comme ancrage sombre (hero, sections d'autorité, footer). Corail `#EC5A57` comme couleur d'action et de marque. Crème `#FBEEDC` comme fond respirant. Jaune `#FFC83D` et cramoisi `#C90032` en accents ponctuels. Voir `design-tokens.md`.

## Composants clés (extraits dans le code)

- **Coquille** : header (nav + switch langue + CTA, menu mobile), footer (colonnes), sticky bar CTA.
- **Sections home** : hero, manifeste, risques GPSR, piliers, services, quiz, pricing, trust (bento + témoignage), CTA final.
- **Boutons** : corail plein, outline clair (sur sombre), outline sombre (sur clair).
- **Quiz** : carte sticker avec tracker de progression, questions Yes/No, résultat (interactivité = Livewire `EligibilityQuiz`, backlog Q1).

## Points d'attention / suite

- **Images** : la maquette utilise des photos Unsplash (placeholders) ; à remplacer par des visuels sous licence, optimisés et locaux (perf + RGPD).
- **Polices** : servies via Bunny Fonts (GDPR-friendly, ADR-015) ; auto-hébergement possible en v2.
- **Interactivité** : menu mobile et fermeture du sticky bar en CSS pur pour l'instant ; quiz statique. À reprendre en Livewire/Alpine.
- **Responsive** : bascules à 640 / 768 / 900 / 1080 px. Le design d'origine est desktop-first ; l'extraction est adaptée mobile.

# Design tokens

> Tokens extraits du design **Festilaw Home** (`source-festilaw-home.dc.html`) et incarnés dans **`resources/css/web/base/tokens.css`** (variables CSS). Ce document est la référence lisible ; le code fait foi.

## Couleurs

| Token | Valeur | Rôle |
|---|---|---|
| `--color-cream` | `#FBEEDC` | Fond clair principal |
| `--color-coral` | `#EC5A57` | Couleur d'action / marque (boutons, accents) |
| `--color-coral-soft` | `#F79184` | Dégradé du quiz |
| `--color-crimson` | `#C90032` | Accent fort (survol, urgence, badges) |
| `--color-navy` | `#0B1E45` | Fond sombre par défaut (`--color-dark`), texte |
| `--color-navy-violet` | `#2E2A6E` | Alternative de thème sombre (bleu-violet) |
| `--color-navy-deep` | `#0A1023` | Fond du bloc CTA final |
| `--color-yellow` | `#FFC83D` | Accent ponctuel (badges, étoiles, surlignage) |
| `--color-blue-700/500/300/100` | `#15315f` / `#2C5A86` / `#5E90BC` / `#8FA6C6` | Dégradés et accents des cartes services |
| `--color-ink-muted` | `#5C5344` | Texte secondaire sur clair |
| `--color-border-sand` | `#E7D9BF` | Bordures des mini-cartes |
| `--color-sand-chip` | `#F4E7D0` | Puces logos marketplaces |
| `--color-success` | `#1F9A63` | Résultat positif du quiz |

> **Le bleu (E1/QO-2) est tranché : navy `#0B1E45` par défaut**, bleu-violet `#2E2A6E` conservé comme option de thème (`--color-dark`).

## Typographies (ADR-015, Bunny Fonts)

| Token | Police | Usage |
|---|---|---|
| `--font-sans` | **Hanken Grotesk** | Corps de texte |
| `--font-display` | **Schibsted Grotesk** | Titres, chiffres, boutons |
| `--font-serif` | **Newsreader** | Accents éditoriaux (Who we are, témoignage) |

Graisses chargées : Hanken 400-700, Schibsted 500-900, Newsreader 400/500 + italiques.

## Rayons

`--radius-sm: 12px` · `--radius: 16px` · `--radius-lg: 26px` · `--radius-xl: 34px` (cartes, boutons, blocs).

## Gabarit

`--max-width: 1300px` · `--gutter: clamp(20px, 5vw, 68px)` (marge horizontale des sections) · `--bp-wide: 1080px` (bascule nav desktop/mobile).

## Où c'est utilisé

- **Fondation** : `resources/css/web/base/` (tokens, reset, typography, animations, buttons).
- **Boutons** : `base/buttons.css` (`.btn`, `.btn--coral`, `.btn--outline-dark`, `.btn--outline-light`, `.btn--sm/lg`).
- **Coquille** : `resources/css/web/layout/` (header, footer, sticky-cta).
- **Page home** : `resources/css/web/home/` (une feuille par section).

# Runbook de mise en production — Festilaw

Ce document explique, étape par étape, comment faire passer le site Festilaw de
l'environnement de développement (local) à la **production réelle** sur
`https://festilaw.com` : vraies clés de paiement et de signature, vraie boîte
mail, tâches automatiques, etc.

Il est écrit pour être suivi **le jour du go-live**, dans l'ordre, sans rien
supposer d'acquis. Chaque étape explique *quoi faire* **et** *pourquoi*.

> **Règle d'or.** On fait ça **en dernier**, une fois que tout le reste est prêt
> et validé en mode test. On avance **une étape à la fois**, et on **vérifie**
> après chaque étape plutôt que de tout enchaîner puis découvrir un problème à la
> fin.

---

## 0. Ce qui est déjà fait (contexte)

Rien à faire ici, c'est juste pour situer.

- Le **parcours de paiement Stripe** a déjà été validé de bout en bout en **mode
  test** (checkout hébergé, prorata, retour, confirmation, emails). Passer en prod
  = remplacer la clé de test par la clé réelle + brancher le webhook.
- La **signature électronique** passe par **SignWell** (les champs signature/date
  sont posés directement sur le contrat). La clé API est déjà branchée sur le
  compte de la cliente.
- Le **renouvellement annuel** est automatique (rappels client + récap admin),
  piloté par une commande planifiée.
- Les **emails** partent aujourd'hui vers une boîte de test **Mailtrap** ; en prod
  il faudra brancher la vraie boîte.

**Particularités de l'hébergement** (important pour comprendre la suite) :

- Hébergement **mutualisé** : on ne peut pas faire tourner de « worker » en
  permanence. C'est pour ça que **les emails sont envoyés en direct** (pendant la
  requête) et que les tâches automatiques passent par **un seul cron**.
- Un **cron unique** (`schedule:run`, voir étape 5) déclenche toutes les tâches
  planifiées (rappels de renouvellement, purge RGPD).

---

## 1. Le fichier `.env` de production

Le fichier `.env` est le fichier de configuration qui contient **tous les
réglages sensibles** (clés, mots de passe, adresses). Il existe **un `.env`
différent par environnement** : celui de ton PC n'est pas celui de la prod.

> ⚠️ **Ne jamais mettre le `.env` dans Git**, ne jamais le partager en clair. Il
> contient des secrets. Le dépôt contient seulement `.env.example` (un modèle
> **sans** valeurs).

Voici **toutes les variables à régler dans le `.env` de prod**. Les étapes 2 à 4
expliquent où récupérer les valeurs secrètes.

### 1.1 L'application

```
APP_NAME="Festilaw"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://festilaw.com
APP_LOCALE=en
APP_KEY=base64:...   # voir ci-dessous
```

- `APP_ENV=production` + `APP_DEBUG=false` : **obligatoire**. En prod on ne veut
  jamais afficher les détails techniques d'une erreur au visiteur (fuite
  d'informations). En dev c'est l'inverse (`local` / `true`).
- `APP_URL=https://festilaw.com` : sert à construire les liens absolus (emails,
  retours de paiement Stripe, liens de dossier). Si c'est faux, les liens des
  emails pointeront au mauvais endroit.
- `APP_KEY` : une clé de chiffrement propre au site. Elle se génère **une seule
  fois** avec `php artisan key:generate` (voir étape 6). **Une fois générée, on n'y
  touche plus** : la changer casserait les données chiffrées / les sessions.

### 1.2 La base de données

```
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

Ce sont les identifiants de la **base MySQL de prod** (fournis par l'hébergeur).
Rien d'inventé ici : on recopie ce que donne le panneau de l'hébergeur.

### 1.3 Les emails (voir aussi étape 4)

```
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@festilaw.com"
MAIL_FROM_NAME="Festilaw"
```

### 1.4 Festilaw (réglages métier)

```
FESTILAW_NOTIFICATION_EMAIL=contact@festilaw.com
```

- `FESTILAW_NOTIFICATION_EMAIL` : l'adresse qui **reçoit les notifications
  internes** (nouvelle demande, paiement reçu, récap des renouvellements à faire,
  retards). C'est la boîte que **Laetitia** consulte. À définir absolument.

Optionnel (des valeurs par défaut existent déjà dans le code) :

```
# FESTILAW_STARTER_AMOUNT_CENTS=33300     # tarif Creator, en centimes (333 €)
# FESTILAW_PRO_AMOUNT_CENTS=120000        # tarif Pro, en centimes (1200 €)
# FESTILAW_RENEWAL_GRACE_DAYS=30          # jours de délai pour régler un renouvellement
```

À ne renseigner que si on veut changer les valeurs par défaut.

### 1.5 Signature (étape 3) et Paiement (étape 2)

Détaillés dans les étapes dédiées ci-dessous.

---

## 2. Stripe — passer en mode réel (live)

Aujourd'hui Stripe tourne en **mode test** (clé `sk_test_...`, aucun argent réel).
Pour la prod, on bascule sur le **mode live** (clé `sk_live_...`, vrais paiements).

> **Rappel du fonctionnement.** Festilaw utilise **Stripe Checkout hébergé** : quand
> le client clique « Payer », il est **redirigé vers la page de Stripe** (Stripe
> gère lui-même le formulaire de carte), puis renvoyé sur le site. C'est pour ça
> qu'on n'a **pas besoin de clé publique** (elle ne sert que pour un formulaire de
> carte intégré dans notre propre page, ce qu'on ne fait pas).

### Étape 2.1 — Activer le compte Stripe

Dans le dashboard Stripe de la cliente : renseigner les informations légales de
l'entreprise et le **compte bancaire (IBAN)** qui recevra les paiements. Tant que
le compte n'est pas « activé », Stripe refuse le mode live.

### Étape 2.2 — Récupérer la clé secrète live

1. Dans le dashboard Stripe, **désactiver le « Mode test »** (interrupteur en haut
   à droite) → on est en **mode live**.
2. Aller dans **Developers → API keys**.
3. Copier la **Secret key** : elle commence par **`sk_live_`**.

> 🔒 Cette clé est **ultra-sensible** (elle permet de créer de vrais paiements).
> Elle ne se met **que** dans le `.env` de prod, jamais dans Git, jamais dans un
> message.

### Étape 2.3 — Créer le webhook (endpoint)

Un **webhook**, c'est une notification que **Stripe envoie à notre serveur** pour
lui dire « ce paiement est bien passé ». Le site sait déjà confirmer le paiement
**au retour** du client (il interroge Stripe). Le webhook est le **filet de
sécurité** pour les cas où le client **ferme l'onglet avant de revenir**, ou pour
les moyens de paiement qui se règlent en différé. **En prod, on le veut.**

1. Dans le dashboard Stripe (toujours en **mode live**) : **Developers → Webhooks
   → Add endpoint**.
2. **Endpoint URL** :

   ```
   https://festilaw.com/webhooks/payment/stripe
   ```

3. **Événements à écouter** (sélectionner exactement ces trois) :

   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
   - `checkout.session.async_payment_failed`

4. Valider, puis **révéler le « Signing secret »** de ce webhook : il commence par
   **`whsec_`**. C'est ce qui permet au site de **vérifier que la notification vient
   bien de Stripe** (et pas d'un imposteur).

### Étape 2.4 — Renseigner le `.env` de prod

```
PAYMENT_PROVIDERS=stripe
STRIPE_SECRET_KEY=sk_live_...........
STRIPE_WEBHOOK_SECRET=whsec_...........
```

- `PAYMENT_PROVIDERS=stripe` : active Stripe comme moyen de paiement (à la place
  du mode « fake » de dev).
- Les deux autres = les secrets récupérés ci-dessus.

---

## 3. SignWell — passer en mode réel

Aujourd'hui SignWell est en **mode test** (`SIGNWELL_TEST_MODE=true`) : documents
de test **gratuits**, **aucun email** envoyé au signataire. En prod, on veut de
**vraies signatures**.

### Étape 3.1 — Le `.env` de prod

```
SIGNATURE_DRIVER=signwell
SIGNWELL_API_KEY=...........        # déjà générée sur le compte de la cliente
SIGNWELL_TEST_MODE=false
```

- `SIGNATURE_DRIVER=signwell` : active SignWell (à la place du mode « fake »).
- `SIGNWELL_TEST_MODE=false` : **mode réel**. Les documents comptent alors dans le
  quota (les 25 premiers documents/mois sont gratuits, puis facturés à l'usage), et
  **les emails de signature partent vraiment**.

### Étape 3.2 — Vérifier le webhook SignWell

Dans les réglages SignWell (API Hook / Event callback URL), l'URL doit être :

```
https://festilaw.com/webhooks/signature
```

C'est **déjà configuré**. Bon à savoir : SignWell **ne demande pas de secret
séparé** pour ce webhook — la vérification se fait avec la clé API elle-même. Il
n'y a donc **rien d'autre** à renseigner côté signature.

---

## 4. Les emails — brancher la vraie boîte

En dev, les mails vont dans une boîte de test (Mailtrap). En prod, ils doivent
partir depuis une **vraie adresse `@festilaw.com`** vers de vrais destinataires.

Dans le `.env` de prod, renseigner les identifiants **SMTP** de la boîte mail
Festilaw (fournis par l'hébergeur du mail) — voir le bloc de l'étape 1.3.

Points d'attention :

- `MAIL_FROM_ADDRESS` doit être une **vraie adresse du domaine** (ex.
  `no-reply@festilaw.com`), sinon les mails risquent de finir en spam.
- Comme les emails sont **envoyés en direct** (pas de file d'attente), il faut un
  **serveur SMTP fiable et rapide** : s'il est lent, le visiteur attend. Une boîte
  pro (celle de l'hébergeur, ou un service d'envoi type SMTP transactionnel) est
  recommandée.

**Emails concernés** (pour tester qu'ils partent bien) : lien de reprise de
dossier, confirmation de paiement, notifications internes à Festilaw, rappels de
renouvellement.

---

## 5. Le cron (tâches automatiques) — indispensable

Certaines choses tournent **toutes seules, tous les jours** :

- **Rappels de renouvellement** (email au client + récap à l'admin, à 07:00).
- **Purge RGPD** des dossiers abandonnés et jamais payés (à 03:00).

Pour que ça fonctionne, l'hébergeur doit exécuter **une seule ligne de cron**, qui
« réveille » le planificateur Laravel chaque minute (c'est Laravel qui décide
ensuite quoi lancer et quand) :

```
* * * * * cd /chemin/vers/le/site && php artisan schedule:run >> /dev/null 2>&1
```

(Adapter `/chemin/vers/le/site` au vrai chemin d'installation.)

> **Sans ce cron : pas de rappels de renouvellement, pas de purge.** C'est une
> étape à ne pas oublier — elle se règle dans le panneau « Cron Jobs » de
> l'hébergeur.

**Pour tester les renouvellements sans attendre janvier**, il existe une commande
qui simule la date :

```
php artisan festilaw:process-renewals --now=2027-01-05 --dry   # aperçu, n'envoie rien
php artisan festilaw:process-renewals --now=2027-01-05         # envoie réellement
```

---

## 6. Déployer le code

À faire à chaque mise en prod du code (et notamment ce jour-là) :

```
# 1. Récupérer la dernière version du code
git pull            # (ou upload des fichiers selon la méthode de déploiement)

# 2. Installer les dépendances PHP en version optimisée production
composer install --no-dev --optimize-autoloader

# 3. Installer et compiler les assets front (CSS/JS)
npm install
npm run build

# 4. Appliquer les évolutions de base de données
php artisan migrate --force
#    (--force = accepter de tourner en production, sans confirmation interactive)

# 5. Mettre en cache la config, les routes et les vues (performances)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **À retenir :** **chaque fois que tu modifies le `.env`**, il faut refaire
> `php artisan config:cache` (ou au minimum `php artisan config:clear`), sinon
> l'ancienne configuration reste en cache et tes nouvelles clés ne sont pas prises
> en compte. C'est **la cause n°1** de « j'ai mis la clé mais ça ne marche pas ».

Bon à savoir : les routes de **dev** (fausse signature, faux paiement) sont
**automatiquement bloquées** hors environnement local — rien à désactiver
manuellement.

---

## 7. Vérifications finales (recette du go-live)

À faire **après** le déploiement, dans l'ordre. Si une vérif échoue, on corrige
avant de passer à la suivante.

1. **Le site s'ouvre** sur `https://festilaw.com`, en **HTTPS** (cadenas), et une
   page d'erreur volontaire n'affiche **aucun détail technique** (preuve que
   `APP_DEBUG=false`).
2. **Signature réelle** : dérouler un vrai parcours jusqu'à la signature, vérifier
   que le contrat arrive par email et que la signature se pose au bon endroit.
3. **Paiement réel** : faire **un vrai paiement** (petit montant possible via un
   dossier réel), avec une **vraie carte**, et vérifier :
   - le dossier passe à **« Payé »** ;
   - le client reçoit son **email de confirmation** (dans une vraie boîte) ;
   - dans le dashboard Stripe (**mode live**) → **Developers → Webhooks**,
     l'événement est **livré avec un statut 200** (preuve que le webhook fonctionne).
4. **Back-office** : se connecter à `/admin`, vérifier que le dossier apparaît, que
   le statut et le renouvellement s'affichent.
5. **Cron** : confirmer côté hébergeur que la ligne de cron est bien active.
   Optionnel : lancer une fois `php artisan festilaw:process-renewals --dry` pour
   vérifier que la commande tourne sans erreur.

---

## 8. Après le go-live (exploitation courante)

- **Voir les paiements** : dashboard Stripe (mode **live**) → **Payments**.
- **Voir les signatures** : compte SignWell → documents.
- **Voir les emails envoyés** : selon le fournisseur SMTP (journal d'envoi).
- **Renouvellements** : tout est automatique via le cron. Pour un contrôle manuel,
  la commande `festilaw:process-renewals` (avec `--dry` pour ne rien envoyer)
  liste ce qui serait fait.
- **En cas de souci** : les logs sont dans `storage/logs/` (dont un journal dédié
  aux paiements et à la signature). C'est le premier endroit où regarder.

---

## 9. Récapitulatif express (le jour J)

Bloc `.env` de prod à compléter :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://festilaw.com
APP_KEY=base64:...            # généré une fois (php artisan key:generate)

DB_DATABASE=...              # base de prod
DB_USERNAME=...
DB_PASSWORD=...

MAIL_MAILER=smtp             # vraie boîte @festilaw.com
MAIL_HOST=...
MAIL_PORT=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@festilaw.com"
MAIL_FROM_NAME="Festilaw"

FESTILAW_NOTIFICATION_EMAIL=contact@festilaw.com

SIGNATURE_DRIVER=signwell
SIGNWELL_API_KEY=...
SIGNWELL_TEST_MODE=false

PAYMENT_PROVIDERS=stripe
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Actions manuelles (hors `.env`) :

- [ ] Compte Stripe activé (société + IBAN)
- [ ] Webhook Stripe créé (`https://festilaw.com/webhooks/payment/stripe`, 3 événements) → `whsec_`
- [ ] Webhook SignWell confirmé (`https://festilaw.com/webhooks/signature`)
- [ ] Cron `schedule:run` activé chez l'hébergeur
- [ ] Déploiement : `composer install --no-dev`, `npm run build`, `migrate --force`, caches
- [ ] Recette : signature réelle + paiement réel (webhook 200) + email reçu OK

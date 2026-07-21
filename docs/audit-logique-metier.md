# Audit de la logique métier — Festilaw

> **Objectif.** Rendre la logique métier « à toute épreuve » : aucun état affiché faux, aucune
> opération perdue silencieusement. En particulier pour le paiement : **la vérité vient du serveur**
> (webhook + re-vérification serveur via l'id Stripe), le retour navigateur n'est qu'un accélérateur
> optimiste, et **un filet doit toujours rattraper** un retour ET un webhook loupés.
>
> **Décisions déjà prises** (cadrent tout le rapport) : (1) audit écrit d'abord, puis implémentation
> par priorité ; (2) on garde les moyens de paiement **asynchrones** (Klarna/Bancontact/MB WAY) → il
> faut gérer un état « en cours » ; (3) on veut **dériver l'état actif/payé du dossier de ses
> paiements** plutôt que le stocker séparément.
>
> Audit réalisé en lecture seule, domaine par domaine (paiement, signature, renouvellement, état du
> dossier, documents/RGPD/admin). Aucune modification de code n'a été faite à ce stade.

---

## 1. Le fil rouge

Les fondations sont **saines** partout : transitions d'état atomiques et idempotentes (updates
conditionnels `WHERE status != …` en transaction), webhooks signés (HMAC), reprise anti-double-débit /
anti-double-signature, auto-confirmation au retour navigateur, logs d'audit dédiés, filets d'erreur sur
les actions admin. **Ce qui manque est structurel et se répète d'un domaine à l'autre :**

1. **Aucun job de réconciliation** (ni paiement, ni signature). C'est *la* cause du bug qu'on vient de
   vivre : si le retour **et** le webhook sont loupés, l'état reste faux pour toujours. C'est le filet
   universel manquant.
2. **L'état du dossier n'est pas dérivé** de la vérité (paiements). `Paid` est écrit en dur, `Refunded`
   n'est jamais posé → un remboursement/chargeback laisse le dossier « actif » indéfiniment.
3. **Transitions non strictement dirigées** : un `succeeded` en retard peut écraser un `failed`, et un
   `async_payment_failed` ne peut pas rétrograder un `succeeded`. L'ordre des webhooks peut mentir.
4. **Cycle de vie Stripe incomplet** : `checkout.session.expired`, `charge.refunded`, `dispute` non
   traités ; pas de clé d'idempotence ; pas de contrainte d'unicité ; pas de dé-doublonnage d'événements.
5. **Pas de visibilité/re-vérification à la demande** (id du paiement, bouton « vérifier le statut »)
   côté client et admin — précisément tes points 3 et 4.

---

## 2. Constats prioritaires (vue d'ensemble)

| # | Domaine | Constat | Sévérité | Priorité |
|---|---|---|---|---|
| 1 | Transverse | **Aucun job de réconciliation** (paiement + signature) : retour ET webhook loupés → état faux à vie | Critique | **P0** |
| 2 | Paiement / État | `Refunded` jamais posé + `Paid` en dur : un **remboursement/litige** laisse le dossier actif | Élevé | **P0** |
| 3 | Paiement | **Transitions non dirigées** : `succeeded` tardif écrase un `failed` (faux payé) ; async pas rétrogradable | Élevé | **P0** |
| 4 | Paiement | Webhook **non rapproché** (`matchPayment` null) → répond 200, Stripe ne réessaie jamais → event perdu | Moyen | **P0** |
| 5 | Renouvellement | Anti-doublon meta : jalon posé **même si l'email a échoué** → rappel client jamais renvoyé, digest admin perdu | Élevé | **P0** |
| 6 | Documents/RGPD | `SubmissionObserver` ne supprime **pas** le PDF contresigné → orphelin + effacement RGPD incomplet | Élevé | **P0** |
| 7 | Signature | `Declined` **jamais persisté** : un refus est invisible (client + admin) | Élevé | **P0** |
| 8 | Paiement | **Concurrence check-then-create** au démarrage checkout (pas de verrou) → double session/double débit | Élevé | **P1** |
| 9 | Paiement | Pas de **clé d'idempotence** Stripe, pas d'unicité `(provider, provider_reference)`, pas de dédup `event.id` | Moyen | **P1** |
| 10 | Paiement | `checkout.session.expired` non géré → paiements « fantômes » `Pending` éternels | Moyen | **P1** |
| 11 | Paiement | Async : pas d'état **« en cours »** distinct ; ne confirmer que sur `async_payment_succeeded` | Élevé | **P1** |
| 12 | Paiement/Admin | Pas de **bouton « vérifier le statut »** ni d'affichage de l'id ; échecs peu visibles (points 3 & 4) | Élevé | **P1** |
| 13 | État | Course **admin ↔ webhook** sur `submissions.status` (update non conditionnel) | Moyen | **P1** |
| 14 | Signature | **Double-clic** sur `sign()` peut créer 2 documents SignWell (dédup non atomique) | Moyen | **P1** |
| 15 | Renouvellement | Pas de relance « en retard » au client ; possible **double débit** la même année (pas de garde d'unicité) | Moyen | **P1** |
| 16 | Renouvellement | Deux définitions parallèles du « à renouveler » (scope SQL vs `RenewalService`) → badge/filtre divergents | Moyen | **P1** |
| 17 | Transverse | Piège **contrainte CHECK d'enum** : toute future valeur casse la prod MySQL en passant les tests SQLite | Moyen | **P1** |
| 18 | Paiement | Retry après échec : le **prorata année 1** change avec le temps → session réutilisée facture l'ancien montant | Moyen | **P1** |
| 19 | Paiement | Binding `{dossier}` filtré `resumable()` → **404 au retour de paiement** si le lien expire pendant le checkout | Moyen | **P1** |
| 20 | Renouvellement | Pas de détection de **cron mort** (aucun heartbeat) | Élevé | **P1** |
| 21 | Signature | Réconciliation absente + pas de `wire:poll` (juste un `wire:init` unique) ; PDF re-téléchargé au rejeu de webhook | Élevé | **P0/P1** |
| 22 | Documents | Disque `local` en `throw=false` → rollback de fichiers/`size()` peut échouer **silencieusement** | Faible | **P2** |
| 23 | Admin | Rate-limit login **par IP seule** (NAT partagé → blocage croisé) | Faible | **P2** |
| 24 | Sécurité | Capability URL **permanente** après paiement (`resume_expires_at=null`), sans révocation/rotation | Moyen | **P2 (métier)** |

---

## 3. Cible d'architecture (le socle robuste)

### 3.1 Machine à états de paiement **dirigée**

Statuts : `Pending` → `Processing` (async en attente) → `Succeeded` **ou** `Failed`/`Expired` ; et
`Succeeded` → `Refunded` (remboursement/litige). Règles :

- On ne passe à **`Succeeded`** que depuis `Pending`/`Processing` **et** seulement si la vérité serveur
  dit *réellement payé* (`payment_status === 'paid'`, ou event `async_payment_succeeded`). Jamais sur
  un simple `completed` d'un moyen asynchrone.
- On ne passe à **`Failed`** que depuis `Pending`/`Processing` (un `Succeeded` ne redevient jamais
  `Failed` par un webhook en retard).
- Le seul chemin `Succeeded` → autre chose est **`Refunded`** (event `charge.refunded`/`dispute`),
  contrôlé.
- Chaque transition s'appuie sur l'**ordre** des événements (event.id/timestamp) pour ne pas être
  réécrite par un webhook rejoué ou désordonné.

### 3.2 Webhook + `checkStatus` = vérité, **réconciliation** = filet ultime

- Le retour navigateur (poll) sert la **vitesse** ; le **webhook** est la vérité serveur ; un
  **`checkStatus`** (re-interrogation Stripe par l'id de session) permet de re-vérifier à la demande.
- **Nouvelle commande planifiée `festilaw:reconcile-payments`** : reprend tout `Payment`
  `Pending`/`Processing` de moins de N jours, appelle `checkStatus`, et confirme/échoue. Couvre en un
  seul filet : onglet fermé, webhook perdu, async en attente. **Idem `festilaw:reconcile-signatures`**
  pour les contrats bloqués en `pending`.
- Un webhook **non rapproché** (`matchPayment` null) sur un event `paid` ne doit **pas** être acquitté
  en 200 (Stripe abandonne) : répondre 500 (retry provider) ou journaliser en « à réconcilier », en
  s'appuyant sur `client_reference_id` (= notre `payment.id`, déjà transmis).

### 3.3 Idempotence & unicité

- **`Idempotency-Key`** déterministe (ex. `payment_id` + tentative) sur `createCheckout` → un retry
  réseau ne crée pas une 2ᵉ session.
- **Index unique** `(provider, provider_reference)`.
- **Table `processed_webhook_events(provider, event_id)`** (unique) : on ignore un event déjà traité
  (défense en profondeur, en plus de l'idempotence par état).
- **Verrou** (`Cache::lock` par dossier) autour du démarrage du checkout → plus de double session sur
  double-clic / double requête. Garde applicative « 1 seul `Pending` par (submission, type) ».

### 3.4 Dérivation de l'état du dossier (approche **hybride**)

- **Dérivé** : le booléen « actif / abonnement en règle » = *« il existe un paiement d'abonnement
  (`StarterSubscription`/`AnnualRenewal`) `Succeeded` **non remboursé** couvrant l'année en cours »* —
  extension directe de `paidThroughYear`. Un **remboursement rend le dossier non-actif
  automatiquement**, sans champ à corriger. Point de vérité **unique** : `Submission::isActive()`, vers
  lequel pointent tous les `in_array($status, [Paid, Completed])` disséminés
  (`StarterJourneyController`, `StarterProjectController`, `SubmissionDetail::isPaid`,
  `StarterDossierFinder`).
- **Reste stocké** : les étapes du funnel **avant** paiement (déjà recalculables depuis `contract` +
  `uploadedDocuments`), et surtout `Cancelled`/`Completed` comme **marqueurs humains** + cache pour les
  index/filtres/tri de la liste admin et la purge RGPD.
- **Conséquence** : `ChangeSubmissionStatusAction` **perd le pouvoir d'écrire un `Paid` artificiel**
  (sinon la dérive qu'on supprime réapparaît) ; on le limite aux marqueurs non dérivés (annuler /
  éventuellement finaliser) ou on le remplace par des actions métier.
- On **évite la dérivation totale** (supprimer `submissions.status`) : elle casserait perfs, filtres,
  index et la purge.

### 3.5 Remboursement / litige

Traiter `charge.refunded` et `charge.dispute.created` → `Payment` `Refunded` → dossier non-actif (via
la dérivation) + notification admin. Aujourd'hui `PaymentStatus::Refunded` **existe mais n'est jamais
écrit**.

### 3.6 Visibilité & re-vérification (points 3 & 4)

- **Un enregistrement de paiement existe déjà dès l'affichage du checkout** (id Stripe mémorisé). À
  compléter :
- **Côté client** (espace « mon projet ») : afficher la **référence** de paiement et son statut ; sur
  un paiement en attente/échoué, un bouton **« Vérifier le statut »** qui appelle `checkStatus` en
  direct et met à jour proprement (message clair, dans la langue du client). **Retry = nouveau
  paiement** (on ne réutilise que si la session est *encore ouverte* ; un paiement échoué/expiré →
  nouvelle session avec **montant recalculé**, crucial pour le prorata année 1).
- **Côté admin** (détail du dossier) : lister **tous** les paiements (réussis **et** échoués) avec
  date, montant, statut, **id Stripe cliquable** vers le dashboard ; sur chaque ligne non-réussie, un
  bouton **« Ré-interroger Stripe »** (`checkStatus`) pour confirmer/infirmer face à un client qui dit
  « j'ai payé ».

---

## 4. Détail par domaine

### 4.1 Paiement

Flux : `PaymentGatewayRegistry` (multi-providers) ; année 1 au prorata (`StartStarterPaymentAction`) et
renouvellement plein tarif (`StartRenewalPaymentAction`) créent une ligne `Payment` `Pending`, appellent
`createCheckout` (Stripe Checkout hosted), stockent le `provider_reference`. Confirmation par 3 canaux :
webhook signé (vérité), poll navigateur au retour (`checkStatus`), et en dev `StarterDevPayController`.

| Scénario | Traitement actuel | Risque / manque | Sévérité |
|---|---|---|---|
| Webhook jamais reçu (endpoint down) | Poll navigateur ~2 min seulement ; **aucun cron de réconciliation** | Paiement encaissé, dossier `AwaitingPayment` à vie | Critique |
| Webhooks désordonnés | `Succeeded` : `WHERE != Succeeded` ; `Failed` : `WHERE = Pending` | Un `succeeded` tardif **écrase un `failed`** (faux payé) | Élevé |
| Remboursement / litige | `Refunded` jamais posé ; events non traités | Dossier reste `Paid`/compté payé | Élevé |
| Async en attente | `checkStatus` renvoie `paid=false` tant que non payé ; pas d'état « en cours » | Poll expire → « on vous écrira » ; perdu si webhook manque | Élevé |
| Double-clic / double session | Réutilisation session ouverte, **mais check-then-create non atomique** | Double `Payment` + double session Stripe → double débit | Élevé |
| Webhook non rapproché | `matchPayment` null → **200** | Event `paid` acquitté et **jamais rejoué** par Stripe | Moyen |
| Expiration session | `checkout.session.expired` non traité | `Payment` `Pending` fantôme éternel | Moyen |
| Idempotence / unicité | Pas de `Idempotency-Key`, index non-unique, pas de dédup event.id | Doublons de sessions/paiements possibles | Moyen |
| Retry (prorata) | Montant figé à la création ; session réutilisée | Facture l'ancien prorata si le mois a changé | Moyen |
| Cohérence paiement ↔ dossier | `SubmissionStatus::Paid` écrit **en dur** | Double source de vérité (voir §3.4) | Élevé |
| Retour de paiement 404 | Binding `{dossier}` filtré `resumable()` | Lien expiré pendant le checkout → 404 au retour | Moyen |

Fondations OK : transitions atomiques/idempotentes, HMAC, 3 canaux, `client_reference_id` transmis.

### 4.2 Signature (SignWell)

Flux : `SignatureManager` (1 provider actif) ; `sign()` → `StartContractSigningAction` (crée le doc,
stocke la référence) → redirection ; retour → `autoConfirmSignature` (`checkStatus`) ; webhook HMAC →
`MarkContractSignedAction` (idempotent). **La vérité vient du serveur**, mais les filets manquent.

| Scénario | Traitement actuel | Risque / manque | Sévérité |
|---|---|---|---|
| Webhook + retour loupés | `wire:init` unique + bouton manuel ; **pas de `wire:poll`, pas de cron** | Contrat **signé chez SignWell reste `pending` à vie** | Élevé |
| Refus (`declined`) | `checkStatus` ne remonte qu'un booléen `signed` ; **`Declined` jamais écrit** | Refus **invisible** (client + admin) ; la vue l'affiche pourtant | Élevé |
| Double-clic `sign()` | Dédup `existingSigningUrl` **après** stockage de la référence | Course → **2 documents SignWell** | Moyen |
| Webhook rejoué | Garde d'état OK, mais `downloadSignedPdf` **avant** le garde | PDF re-téléchargé/ré-écrit à chaque rejeu (gaspillage) | Faible |
| Driver `fake` en prod par erreur | Route signature résout le driver par défaut | `parseWebhook` fake accepterait un payload non signé | Moyen |

### 4.3 Renouvellement

Entièrement **dérivé des paiements** (`RenewalService`) — c'est le prototype de la dérivation voulue.
Cron quotidien (rappels client + digests admin), paiement plein tarif depuis l'espace dossier, retour
`?renewal_return=1` (bug d'origine corrigé).

| Scénario | Traitement actuel | Risque / manque | Sévérité |
|---|---|---|---|
| Rappel client / digest admin | Jalon meta posé **même si l'email a échoué** | Rappel **jamais renvoyé** ; digest admin **perdu** | Élevé |
| Cron non exécuté | Aucune trace de dernière exécution | Renouvellements silencieusement non rappelés | Élevé |
| Client paie, canaux loupés | Poll `renewal_return` + webhook prod | Si webhook échoue durablement → `Pending` sans rattrapage (pas de réconciliation) | Moyen |
| Relance « en retard » | Rappel **1×/an** dans le 1er état vu | Pas de 2ᵉ nudge quand on bascule « en retard » | Moyen |
| Double débit même année | Anti-double-clic OK, mais 2 lignes `Succeeded` possibles | Débit double réel possible | Moyen |
| Badge vs filtre | Scope SQL (sans fallback `paid_at`) vs `RenewalService` (avec) | Liste/badge peuvent diverger | Moyen |
| Saute une année | Facture **une seule** année (la courante) | Décision métier à trancher (voir §5) | Moyen |

### 4.4 État du dossier & parcours

`SubmissionStatus` : New/InProgress/AwaitingDocuments/AwaitingPayment/Paid/Completed/Cancelled. Le
funnel est **dérivé** du statut (`step()`) ; le renouvellement est **déjà** dérivé des paiements.

| Scénario | Traitement actuel | Risque / manque | Sévérité |
|---|---|---|---|
| Remboursement → non-actif | `MarkPaymentFailed` ne rétrograde jamais ; `Refunded` non géré | Dossier `Paid` actif à vie après remboursement | Élevé |
| Dedup email cross-pack | Un seul dossier ouvert par email (Starter+Pro) | Client **actif Creator ne peut pas souscrire Pro** en self-service | Élevé |
| Course admin ↔ webhook | Update `submissions.status` **non conditionnel** | Dernier écrivain gagne, état final non déterministe | Moyen |
| Contrainte CHECK enum | Valeurs figées le 16/07 | Toute **future** valeur casse la prod MySQL (tests SQLite passent) | Moyen |
| Capability URL | `resume_token` permanent après paiement | Accès permanent aux téléchargements, sans révocation | Moyen |

**Décision « dériver » — reco** : hybride (cf. §3.4). Dériver l'« actif » (source unique
`Submission::isActive()`), garder `Cancelled`/`Completed` stockés + les étapes pré-paiement recalculées
des relations ; retirer à l'admin le pouvoir d'écrire un `Paid` artificiel.

### 4.5 Documents, RGPD, admin & notifications

Dépôt documents avec rollback des fichiers ; purge RGPD gardée (payés exclus) ; actions admin
désormais toutes avec filet `Throwable` → toast ; emails périphériques non bloquants + loggés.

| Scénario | Traitement actuel | Risque / manque | Sévérité |
|---|---|---|---|
| Suppression / purge dossier | `SubmissionObserver` supprime docs + mandat signé | **PDF contresigné jamais supprimé** → orphelin + RGPD incomplet | Élevé |
| Rollback fichiers | Disque `local` en `throw=false` | `delete()`/`size()` échouent **silencieusement** (orphelins non loggés) | Faible |
| Actions admin en échec | `try/catch (Throwable)` → `reportAdminError` (log + toast) | **Complet et cohérent** | OK |
| Purge d'un payé | Filtrée type+statuts non-payés+expiré | Gardes correctes | OK |
| Login bruteforce | Rate-limit **par IP seule** | NAT partagé → blocage croisé | Faible |
| Quiz | Validation + throttle + anonyme (RGPD) | Sain | OK |

---

## 5. Décisions métier (tranchées avec Laetitia, 2026-07-21)

1. **Upgrade de pack — RETENU comme feature.** Un client peut **upgrader Creator↔Pro**. Réalisable
   **côté client** (depuis son espace dossier) **et côté admin** (l'admin déclenche l'upgrade et envoie
   le **lien de paiement par mail** si le client le demande hors ligne). Reste **un dossier par
   client** ; la dédup n'empêche donc que les doublons du même client, l'upgrade gère le changement de
   pack + la facturation du nouveau plein tarif. → nouveau lot (P1 feature).
2. **Saut d'année → l'année en cours uniquement.** On facture le plein tarif de l'année courante ; les
   années non payées sont ignorées (le service avait lapsé). À documenter (commentaire + test).
3. **Lien magique → régénéré à chaque demande.** Chaque envoi de lien (client via « retrouver mon
   projet », ou admin via « renvoyer le lien ») **régénère le `resume_token`** → l'ancien lien est
   invalidé. Plus de lien permanent, sécurité par construction, sans révocation manuelle. → intégré au
   socle (petit lot).

---

## 6. Plan d'implémentation priorisé

### P0 — Confiance (à faire en premier)

1. **Réconciliation** : `festilaw:reconcile-payments` + `festilaw:reconcile-signatures` (cron), le
   filet universel. + webhook non rapproché → 500/à-réconcilier (plus de perte silencieuse).
2. **Machine à états dirigée** + gestion **remboursement/litige** (`Refunded`) + async confirmé
   uniquement sur `async_payment_succeeded` + `expired`→`Failed`.
3. **Dérivation de l'« actif »** (`Submission::isActive()`), point de vérité unique ; l'admin ne fabrique
   plus de `Paid`.
4. **Renouvellement** : ne poser les jalons meta (rappel client, digests admin) **qu'après envoi
   réussi**.
5. **RGPD** : `SubmissionObserver` supprime aussi le PDF contresigné.
6. **Signature** : persister `Declined`/`Expired` (DTO enrichi) + réconciliation.

### P1 — Robustesse

7. Idempotence Stripe (`Idempotency-Key`), index unique `(provider, provider_reference)`, table
   `processed_webhook_events`, verrou anti-double-session.
8. **Points 3 & 4** : bouton « vérifier le statut » (client + admin), affichage de l'id + tous les
   paiements (réussis/échoués) avec re-interrogation Stripe par ligne.
9. Retry = nouveau paiement avec **montant recalculé** (prorata).
10. Renouvellement : relance « en retard » distincte ; garde/unicité anti-double-débit même année ;
    aligner scope SQL et `RenewalService` ; **heartbeat de cron**.
11. Signature : verrou anti-double-document ; PDF non re-téléchargé au rejeu.
12. Enum CHECK : helper de refresh réutilisable + test `cases()` ↔ contrainte.
13. Binding `{dossier}` non filtré `resumable()` sur les URLs de retour de paiement.
14. Course admin ↔ webhook : rendue caduque par la dérivation (sinon update conditionnel + log).

### P2 — Finitions

15. Storage : contrôler les retours de `delete()`/`size()` (logs) ; envisager `throw=true` sur le
    rollback.
16. Rate-limit login par **IP + email**.
17. `APP_TIMEZONE=Europe/Paris` explicite.
18. UX annulation/expiration de checkout (message clair).
19. (métier) Révocation/rotation du capability token, si retenu en §5.

---

*Prochaine étape : valider ce plan et les 4 questions métier, puis j'implémente domaine par domaine en
commençant par le socle P0 (réconciliation + machine à états dirigée + dérivation), chaque lot testé et
vérifié.*

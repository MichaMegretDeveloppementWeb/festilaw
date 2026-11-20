<?php

declare(strict_types=1);

namespace App\Enums\Analytics;

use App\Enums\Payment\PaymentType;
use App\Enums\Quiz\QuizOutcome;
use App\Enums\Submission\SubmissionType;

/**
 * Les evenements nommes du site, decrits une seule fois (plan SC8 approuve par Micha le 20/11). La valeur est
 * le nom technique, que les vues ecrivent dans `data-track-event` et que le serveur enregistre : il ne change
 * jamais. Le libelle est celui qu'on lit dans Audience et Marketing.
 *
 * Deux familles : les clics qui comptent (aucun n'est une conversion) et les faits que le serveur enregistre
 * quand ils sont acquis. Seuls les faits de vente et de prise de contact sont des conversions, et seul
 * l'argent porte des points : 1 point pour 10 euros de valeur annuelle au prix catalogue, sans le prorata de
 * la premiere annee (Creator Pack 333 euros, Pro Pack 1 200, passage au Pro 867, audit Scale 75). Ces points
 * se revoient ici si les tarifs changent.
 */
enum AudienceEvent: string
{
    case HeaderToOffers = 'header.to_offers';
    case HomeToOffers = 'home.to_offers';
    case ClosingToOffers = 'closing.to_offers';
    case CreatorChosen = 'offer.creator.chosen';
    case ProChosen = 'offer.pro.chosen';
    case ScaleChosen = 'offer.scale.chosen';
    case HomeToQuiz = 'home.to_quiz';
    case QuizAnswered = 'quiz.answered';
    case ArticleToQuiz = 'article.to_quiz';
    case ArticleToPricing = 'article.to_pricing';
    case SignatureOpened = 'signature.opened';
    case PaymentOpened = 'payment.opened';
    case UpgradeOpened = 'upgrade.opened';
    case UpgradeConfirmed = 'upgrade.confirmed';

    case QuizConcerned = 'quiz.concerned';
    case QuizExcluded = 'quiz.excluded';
    case QuizNotConcerned = 'quiz.not_concerned';
    case CreatorOpened = 'creator.opened';
    case ProOpened = 'pro.opened';
    case DossierLinkRequested = 'dossier.link_requested';
    case DocumentsSent = 'dossier.documents_sent';
    case ContractSigned = 'dossier.contract_signed';
    case PackSwitched = 'dossier.pack_switched';
    case CreatorPaid = 'creator.paid';
    case ProPaid = 'pro.paid';
    case UpgradePaid = 'upgrade.paid';
    case CreatorRenewalPaid = 'renewal.creator.paid';
    case ProRenewalPaid = 'renewal.pro.paid';
    case ScaleAuditPaid = 'scale.audit_paid';
    case PaymentCancelled = 'payment.cancelled';
    case ScaleRequested = 'scale.requested';
    case ScaleBooked = 'scale.booked';
    case ContactSent = 'contact.sent';

    public function label(): string
    {
        return match ($this) {
            self::HeaderToOffers => __('Vers les offres, depuis l\'en-tête'),
            self::HomeToOffers => __('Vers les offres, depuis l\'accueil'),
            self::ClosingToOffers => __('Vers les offres, depuis la fin de page'),
            self::CreatorChosen => __('Choix du Creator Pack'),
            self::ProChosen => __('Choix du Pro Pack'),
            self::ScaleChosen => __('Choix du Scale Pack'),
            self::HomeToQuiz => __('Vers le quiz, depuis l\'accueil'),
            self::QuizAnswered => __('Réponse au quiz'),
            self::ArticleToQuiz => __('Vers le quiz, depuis un article'),
            self::ArticleToPricing => __('Vers les tarifs, depuis un article'),
            self::SignatureOpened => __('Ouverture de la signature'),
            self::PaymentOpened => __('Ouverture du paiement'),
            self::UpgradeOpened => __('Passage au Pro Pack, ouverture'),
            self::UpgradeConfirmed => __('Passage au Pro Pack, confirmation'),
            self::QuizConcerned => __('Quiz terminé, concerné'),
            self::QuizExcluded => __('Quiz terminé, catégorie exclue'),
            self::QuizNotConcerned => __('Quiz terminé, non concerné'),
            self::CreatorOpened => __('Dossier Creator Pack ouvert'),
            self::ProOpened => __('Dossier Pro Pack ouvert'),
            self::DossierLinkRequested => __('Lien du dossier redemandé'),
            self::DocumentsSent => __('Documents du dossier envoyés'),
            self::ContractSigned => __('Contrat signé'),
            self::PackSwitched => __('Formule changée avant paiement'),
            self::CreatorPaid => __('Creator Pack payé'),
            self::ProPaid => __('Pro Pack payé'),
            self::UpgradePaid => __('Passage au Pro Pack payé'),
            self::CreatorRenewalPaid => __('Renouvellement Creator Pack payé'),
            self::ProRenewalPaid => __('Renouvellement Pro Pack payé'),
            self::ScaleAuditPaid => __('Audit Scale Pack payé'),
            self::PaymentCancelled => __('Paiement abandonné'),
            self::ScaleRequested => __('Demande Scale Pack envoyée'),
            self::ScaleBooked => __('Rendez-vous Scale Pack demandé'),
            self::ContactSent => __('Message de contact envoyé'),
        };
    }

    /** Les points d'une occurrence : un paiement seulement, 1 point pour 10 euros de valeur annuelle. */
    public function points(): ?int
    {
        return match ($this) {
            self::CreatorPaid, self::CreatorRenewalPaid => 33,
            self::ProPaid, self::ProRenewalPaid => 120,
            self::UpgradePaid => 87,
            self::ScaleAuditPaid => 8,
            default => null,
        };
    }

    /** Les faits de vente et de prise de contact ; jamais un clic. */
    public function isConversion(): bool
    {
        return match ($this) {
            self::CreatorOpened, self::ProOpened, self::ScaleRequested, self::ScaleBooked, self::ContactSent,
            self::CreatorPaid, self::ProPaid, self::UpgradePaid, self::CreatorRenewalPaid, self::ProRenewalPaid, self::ScaleAuditPaid => true,
            default => false,
        };
    }

    /** Le dossier ouvert d'une formule du parcours STARTER (Creator ou Pro). */
    public static function openedFor(SubmissionType $pack): self
    {
        return $pack === SubmissionType::Pro ? self::ProOpened : self::CreatorOpened;
    }

    /** Le resultat du quiz, tel que le serveur l'a calcule. */
    public static function quizResult(QuizOutcome $outcome): self
    {
        return match ($outcome) {
            QuizOutcome::Concerned => self::QuizConcerned,
            QuizOutcome::Excluded => self::QuizExcluded,
            QuizOutcome::NotConcerned => self::QuizNotConcerned,
        };
    }

    /** Le paiement reussi, d'apres son type et la formule du dossier qui le porte. */
    public static function paidFor(PaymentType $type, SubmissionType $pack): self
    {
        $pro = $pack === SubmissionType::Pro;

        return match ($type) {
            PaymentType::StarterSubscription => $pro ? self::ProPaid : self::CreatorPaid,
            PaymentType::AnnualRenewal => $pro ? self::ProRenewalPaid : self::CreatorRenewalPaid,
            PaymentType::PackUpgrade => self::UpgradePaid,
            PaymentType::ScaleAudit => self::ScaleAuditPaid,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums\Blog\Generation;

/**
 * La sorte d'un echec d'appel au fournisseur d'IA (controle du 20/11), qui decide de ce que fait le moteur :
 * reessayer tout de suite, attendre, ou s'arreter sans repayer un essai qui ne changerait rien. Jamais
 * enregistree en base.
 */
enum BlogAiFailureKind: string
{
    /** Credit epuise ou limite de la cle atteinte : rien a reessayer avant un rechargement. */
    case Credit = 'credit';

    /** La cle du site est refusee (HTTP 401). */
    case KeyRejected = 'key_rejected';

    /** Le modele n'existe plus, ou plus aucun fournisseur ne le sert avec ce que le site demande. */
    case ModelUnavailable = 'model_unavailable';

    /** Le fournisseur refuse ce texte (moderation, HTTP 403 sans rapport avec une limite). */
    case Refused = 'refused';

    /** Panne passagere (429, 408, 5xx, coupure, delai depasse) : reessayer plus tard, sans tenir le verrou. */
    case Transient = 'transient';

    /** Reponse structuree coupee a sa limite, encore apres une reprise avec moins de reflexion. */
    case CutOff = 'cut_off';

    /** L'appel n'est pas parti : son cout maximal ne tenait plus avant l'arret de securite. */
    case OverBudget = 'over_budget';

    /** La generation a ete arretee a la main pendant l'etape : l'appel suivant n'est pas parti. */
    case Cancelled = 'cancelled';

    /** Tout le reste (reponse illisible, erreur inattendue) : trois essais rapproches, comme avant. */
    case Other = 'other';

    /** Un nouvel essai, meme lointain, ne changerait rien. */
    public function isFinal(): bool
    {
        return in_array($this, [self::Credit, self::KeyRejected, self::ModelUnavailable, self::Refused, self::CutOff], true);
    }
}

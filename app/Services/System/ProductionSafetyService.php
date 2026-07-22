<?php

declare(strict_types=1);

namespace App\Services\System;

/**
 * Verifie que la configuration est apte a la production : liste ce qui, s'il etait deploye tel quel,
 * ferait tourner le site en simulation (faux prestataires) ou fuiter des informations. Service de
 * calcul pur (lit la config, aucune ecriture) : la commande festilaw:check-production l'affiche pour la
 * checklist go-live (code de sortie non nul si manquements), et le middleware EnsureProductionIsConfigured
 * l'utilise pour tracer un avertissement NON bloquant en prod.
 */
final class ProductionSafetyService
{
    /**
     * @return list<string> les manquements bloquants pour la production (vide si tout est OK)
     */
    public function violations(): array
    {
        $violations = [];

        // On EXIGE positivement les vrais prestataires (au lieu de bannir un mode "fake"), pour que la
        // regle reste valable meme si les doubles de dev disparaissent.
        if (! in_array('stripe', (array) config('payment.enabled', []), true)) {
            $violations[] = 'Stripe n\'est pas le provider de paiement actif (PAYMENT_PROVIDERS doit valoir "stripe").';
        } else {
            if ((string) config('payment.drivers.stripe.secret_key') === '') {
                $violations[] = 'STRIPE_SECRET_KEY est manquante.';
            }
            if ((string) config('payment.drivers.stripe.webhook_secret') === '') {
                $violations[] = 'STRIPE_WEBHOOK_SECRET est manquante.';
            }
        }

        if (config('signature.default') !== 'signwell') {
            $violations[] = 'SignWell n\'est pas le driver de signature actif (SIGNATURE_DRIVER doit valoir "signwell").';
        } else {
            if ((string) config('signature.drivers.signwell.api_key') === '') {
                $violations[] = 'SIGNWELL_API_KEY est manquante.';
            }
            if ((bool) config('signature.drivers.signwell.test_mode', false)) {
                $violations[] = 'SignWell est en mode test (SIGNWELL_TEST_MODE doit valoir false en production).';
            }
        }

        if (in_array((string) config('mail.default'), ['log', 'array'], true)) {
            $violations[] = 'MAIL_MAILER vaut "'.config('mail.default').'" : les emails ne partent pas. Configurez un vrai transport.';
        }

        if ((bool) config('app.debug') === true) {
            $violations[] = 'APP_DEBUG est active : fuite d\'informations en cas d\'erreur. Mettez APP_DEBUG=false.';
        }

        return $violations;
    }
}

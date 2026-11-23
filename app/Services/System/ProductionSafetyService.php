<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Repositories\Blog\OpenRouterKeyRepository;

/**
 * Verifie que la configuration est apte a la production : liste ce qui, s'il etait deploye tel quel,
 * ferait tourner le site en simulation (faux prestataires) ou fuiter des informations. Lecture seule (la
 * config, et la cle OpenRouter des reglages pour le rappel) : la commande festilaw:check-production l'affiche
 * pour la checklist go-live et a la fin de deploy.sh (code de sortie non nul si manquements), et le middleware
 * EnsureProductionIsConfigured l'utilise pour journaliser une erreur NON bloquante en prod.
 */
final class ProductionSafetyService
{
    public function __construct(private readonly OpenRouterKeyRepository $openRouterKeys) {}

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

        // Blog : sans GD et son support WebP, aucune image d'article ne peut etre traitee.
        if (! $this->canProcessBlogImages()) {
            $violations[] = 'L\'extension PHP GD avec le support WebP est absente : les images du blog ne peuvent pas etre traitees.';
        }

        // Banque d'images et mesure d'audience (SC9) : sans elles, le site tourne, mais les brouillons generes
        // arrivent sans photo, et la mesure ne compte rien. La cle OpenRouter n'est plus ici : elle vit dans les
        // reglages de la generation (voir reminders()).
        if ((string) config('services.pixabay.key') === '') {
            $violations[] = 'PIXABAY_API_KEY est manquante : les brouillons generes arrivent sans photo de la banque d\'images.';
        }

        if (config('blog.ai.pipeline') !== 'editorial') {
            $violations[] = 'BLOG_AI_PIPELINE vaut "'.config('blog.ai.pipeline').'" : la generation ne passe pas par le pipeline editorial. Laissez la variable absente.';
        }

        if (config('analytics.enabled') !== true) {
            $violations[] = 'La mesure d\'audience est eteinte : ANALYTICS_ENABLED doit valoir true.';
        }

        // Surveillance (controle du 20/11) : sans adresse technique, le rapport d'erreurs du matin ne part
        // pas, et un journal en fichier unique grossit sans fin sur l'hebergement.
        if (trim((string) config('festilaw.tech_alert_email')) === '') {
            $violations[] = 'FESTILAW_TECH_ALERT_EMAIL est vide, donc le rapport d\'erreurs du matin ne part pas et les alertes techniques vont a l\'adresse de notification.';
        }

        if ($this->logsInOneFile()) {
            $violations[] = 'Le journal tient en un seul fichier qui grossit sans fin. Mettez LOG_STACK=daily et LOG_DAILY_DAYS=30.';
        }

        return $violations;
    }

    /**
     * Ce qui manque encore sans etre une faute de configuration : la cle OpenRouter se colle dans les reglages de
     * la generation, une fois le site en ligne (decision de Micha du 23/11). La commande la rappelle sans echouer,
     * et la verification du matin la signale tant qu'elle manque.
     *
     * @return list<string>
     */
    public function reminders(): array
    {
        return $this->openRouterKeys->current() === null
            ? ["Rappel. Aucune cle OpenRouter n'est enregistree. Collez-la dans le back-office (Blog, Generation IA, Reglages, section « Cle d'acces OpenRouter »)."]
            : [];
    }

    private function logsInOneFile(): bool
    {
        $default = (string) config('logging.default');
        $channels = $default === 'stack' ? (array) config('logging.channels.stack.channels', []) : [$default];

        return in_array('single', $channels, true);
    }

    private function canProcessBlogImages(): bool
    {
        return extension_loaded('gd') && function_exists('imagewebp') && function_exists('imagecreatefromwebp');
    }
}

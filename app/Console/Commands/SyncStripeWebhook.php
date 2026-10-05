<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\StripeWebhookRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Complete l'endpoint webhook Stripe du site avec les evenements que le site traite : paiements, sessions
 * expirees, remboursements et litiges perdus. Sans eux, un remboursement fait dans le tableau de bord Stripe
 * ne remonte jamais au site (le paiement resterait "reussi", le dossier actif). Ajoute seulement les
 * evenements manquants : n'en retire jamais, ne cree jamais d'endpoint (son secret de signature, donne a la
 * creation, se met dans .env : procedure docs/go-live.md). Le secret d'un endpoint existant ne change pas.
 *
 * Lancee par deploy.sh a chaque deploiement (non bloquante). Refuse une URL non publique (festilaw.test,
 * localhost, http). Option --dry : affiche ce qui serait ajoute, sans rien modifier.
 */
final class SyncStripeWebhook extends Command
{
    protected $signature = 'festilaw:stripe-webhook {--dry : Simulation, sans modification}';

    protected $description = 'Ajoute a l\'endpoint webhook Stripe du site les evenements traites (remboursements, litiges...).';

    public function handle(StripeWebhookRegistry $registry): int
    {
        if (! in_array('stripe', (array) config('payment.enabled'), true) || empty(config('payment.drivers.stripe.secret_key'))) {
            $this->warn('Stripe n\'est pas actif ou sa cle secrete manque : rien a synchroniser.');

            return self::SUCCESS;
        }

        $url = route('webhooks.payment', ['provider' => 'stripe']);
        if (! $this->isPublic($url)) {
            $this->error("URL de webhook non publique ({$url}) : rien n'est envoye a Stripe. A lancer sur le serveur de production.");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');

        try {
            $result = $registry->ensureEvents($url, $dry);
        } catch (Throwable $e) {
            $this->error('Synchronisation du webhook Stripe impossible : '.$e->getMessage().$this->providerAnswer($e));

            return self::FAILURE;
        }

        if ($result->endpointId === null) {
            $this->warn("Aucun endpoint webhook Stripe pour {$url} : a creer dans le tableau de bord Stripe (docs/go-live.md, etape 2.3), puis mettre son secret dans STRIPE_WEBHOOK_SECRET.");

            return self::FAILURE;
        }

        $suffix = $dry ? ' [DRY-RUN]' : '';
        $this->info(match (true) {
            $result->listensToAll => "Endpoint Stripe {$result->endpointId} : il ecoute deja tous les evenements.{$suffix}",
            $result->addedEvents === [] => "Endpoint Stripe {$result->endpointId} : evenements a jour.{$suffix}",
            default => "Endpoint Stripe {$result->endpointId} : ".($dry ? 'ajouterait' : 'evenements ajoutes').' '.implode(', ', $result->addedEvents).".{$suffix}",
        });

        return self::SUCCESS;
    }

    /** La reponse de Stripe (code + message) quand l'appel a ete refuse : sans elle, on devine. */
    private function providerAnswer(Throwable $e): string
    {
        $previous = $e->getPrevious();
        if (! $previous instanceof RequestException) {
            return '';
        }

        return ' (Stripe HTTP '.$previous->response->status().' : '.mb_substr(trim($previous->response->body()), 0, 300).')';
    }

    /** https, et pas un domaine de developpement local. */
    private function isPublic(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        return str_starts_with($url, 'https://')
            && $host !== ''
            && $host !== 'localhost'
            && ! str_ends_with($host, '.test')
            && ! str_ends_with($host, '.localhost')
            && filter_var($host, FILTER_VALIDATE_IP) === false;
    }
}

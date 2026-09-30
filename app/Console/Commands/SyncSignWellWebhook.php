<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Signature\SignWellWebhookRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Synchronise le webhook SignWell du site : retrouve le(s) webhook(s) SignWell qui appellent
 * /webhooks/signature, en cree un s'il n'y en a aucun, et memorise leurs ids. SignWell signe chaque
 * evenement avec l'id du webhook (cle HMAC) : sans cette synchronisation, les webhooks sont refuses.
 * Ne supprime jamais rien.
 *
 * Lancee par deploy.sh a chaque deploiement (non bloquante). Refuse une URL non publique (festilaw.test,
 * localhost, http) : jamais de webhook local enregistre sur un compte SignWell. Option --dry : affiche
 * ce qui existe sans rien creer ni ecrire.
 *
 * Option --recreate (a lancer a la main, une fois) : remplace le(s) webhook(s) du site par un neuf, quand
 * SignWell a cesse de l'appeler (desactive apres des refus en serie, etat que son API n'expose pas).
 * SignWell n'accepte qu'un webhook par URL : l'ancien est supprime d'abord, puis le neuf cree ; si la
 * creation echoue, relancer la commande sans option le recree. Les webhooks d'autres URL ne sont jamais
 * touches.
 */
final class SyncSignWellWebhook extends Command
{
    protected $signature = 'festilaw:signwell-webhook
        {--dry : Simulation, sans creation, suppression ni ecriture}
        {--recreate : Remplace le(s) webhook(s) du site par un neuf (SignWell a cesse de l\'appeler)}';

    protected $description = 'Retrouve ou cree le webhook SignWell du site et memorise son id (cle de verification).';

    public function handle(SignWellWebhookRegistry $registry): int
    {
        if (config('signature.default') !== 'signwell' || empty(config('signature.drivers.signwell.api_key'))) {
            $this->warn('SignWell n\'est pas le prestataire actif ou sa cle API manque : rien a synchroniser.');

            return self::SUCCESS;
        }

        $callbackUrl = route('webhooks.signature');
        if (! $this->isPublic($callbackUrl)) {
            $this->error("URL de webhook non publique ({$callbackUrl}) : aucun webhook enregistre. A lancer sur le serveur de production.");

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $recreate = (bool) $this->option('recreate');

        try {
            $result = $recreate ? $registry->recreate($callbackUrl, $dry) : $registry->sync($callbackUrl, $dry);
        } catch (Throwable $e) {
            $this->error('Synchronisation du webhook SignWell impossible : '.$e->getMessage().$this->providerAnswer($e));
            if ($recreate) {
                $this->warn('Si l\'ancien webhook a deja ete supprime, relancer sans option recree le webhook manquant : php artisan festilaw:signwell-webhook');
            }

            return self::FAILURE;
        }

        $ids = implode(', ', array_map($this->mask(...), $result->ids));
        $deleted = implode(', ', array_map($this->mask(...), $result->deleted));

        if ($recreate) {
            $this->info($dry
                ? 'Recreation : '.($deleted !== '' ? "supprimerait {$deleted} et " : '')."creerait un webhook neuf pour {$callbackUrl}. [DRY-RUN]"
                : "Webhook SignWell recree pour {$callbackUrl} (id {$ids})".($deleted !== '' ? ", ancien(s) supprime(s) : {$deleted}." : '.'));

            return self::SUCCESS;
        }

        $this->info(match (true) {
            $result->created => "Webhook SignWell cree pour {$callbackUrl} (id {$ids}).",
            $result->ids !== [] => "Webhook SignWell trouve pour {$callbackUrl} (id {$ids}).".($dry ? ' [DRY-RUN]' : ''),
            default => "Aucun webhook SignWell pour {$callbackUrl} : il serait cree. [DRY-RUN]",
        });

        return self::SUCCESS;
    }

    /** La reponse de SignWell (code + message) quand l'appel a ete refuse : sans elle, on devine. */
    private function providerAnswer(Throwable $e): string
    {
        $previous = $e->getPrevious();
        if (! $previous instanceof RequestException) {
            return '';
        }

        return ' (SignWell HTTP '.$previous->response->status().' : '.mb_substr(trim($previous->response->body()), 0, 300).')';
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

    /** L'id est la cle de verification des webhooks : jamais affiche en entier. */
    private function mask(string $id): string
    {
        return strlen($id) > 8 ? substr($id, 0, 4).'…'.substr($id, -2) : '…';
    }
}

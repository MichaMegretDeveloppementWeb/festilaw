<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Signature\SignWellWebhookRegistry;
use Illuminate\Console\Command;
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
 */
final class SyncSignWellWebhook extends Command
{
    protected $signature = 'festilaw:signwell-webhook {--dry : Simulation, sans creation ni ecriture}';

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

        try {
            $result = $registry->sync($callbackUrl, $dry);
        } catch (Throwable $e) {
            $this->error('Synchronisation du webhook SignWell impossible : '.$e->getMessage());

            return self::FAILURE;
        }

        $ids = implode(', ', array_map($this->mask(...), $result->ids));

        $this->info(match (true) {
            $result->created => "Webhook SignWell cree pour {$callbackUrl} (id {$ids}).",
            $result->ids !== [] => "Webhook SignWell trouve pour {$callbackUrl} (id {$ids}).".($dry ? ' [DRY-RUN]' : ''),
            default => "Aucun webhook SignWell pour {$callbackUrl} : il serait cree. [DRY-RUN]",
        });

        return self::SUCCESS;
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

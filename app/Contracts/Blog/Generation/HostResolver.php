<?php

declare(strict_types=1);

namespace App\Contracts\Blog\Generation;

/**
 * Resout un nom d'hote en adresses IP, pour verifier avant une requete sortante qu'une adresse venue d'un
 * modele ne vise ni le reseau local ni le serveur lui-meme. Derriere une interface pour que les tests ne
 * dependent pas du DNS.
 */
interface HostResolver
{
    /** @return list<string> les adresses IPv4 et IPv6 du nom, vide s'il ne se resout pas */
    public function resolve(string $host): array;
}

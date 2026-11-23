<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * La cle OpenRouter du site, collee par la cliente dans les reglages de la generation (decision de Micha du 23/11 :
 * elle quitte le .env). Verifiee chez OpenRouter avant d'etre enregistree, avec sa date d'expiration lue a ce
 * moment. La cle elle-meme ne sort jamais du serveur : l'ecran n'en montre que le debut et la fin.
 */
final readonly class OpenRouterKey
{
    /** Debut montre : le prefixe d'OpenRouter (« sk-or-v1- ») et quatre caracteres. */
    private const SHOWN_START = 13;

    private const SHOWN_END = 4;

    public function __construct(
        #[\SensitiveParameter]
        public string $secret,
        public ?CarbonImmutable $expiresAt,
        public CarbonImmutable $savedAt,
    ) {}

    public function masked(): string
    {
        $length = Str::length($this->secret);

        if ($length <= self::SHOWN_START + self::SHOWN_END + 4) {
            return '…'.Str::substr($this->secret, -self::SHOWN_END);
        }

        return Str::substr($this->secret, 0, self::SHOWN_START).'…'.Str::substr($this->secret, -self::SHOWN_END);
    }
}

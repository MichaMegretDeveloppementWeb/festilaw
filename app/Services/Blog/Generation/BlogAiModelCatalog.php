<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

/**
 * Les modeles que le back-office propose (config/blog.php, section ai) : ceux qui ecrivent du texte, et ceux
 * qui generent des images. La liste est fermee : la personne choisit dans une liste, sous le nom commercial
 * du modele, et ne saisit jamais un identifiant. Calcul pur, aucune requete.
 */
final readonly class BlogAiModelCatalog
{
    /** Le reglage « modele par etape » qui demande un modele d'image ; tous les autres demandent du texte. */
    public const IMAGE_SETTING = 'image';

    /**
     * @param  array<string, array{name?: string, provider?: string, input?: int, output?: int}>  $textModels
     * @param  array<string, array{name?: string, provider?: string, reserve_microusd?: int}>  $imageModels
     */
    public function __construct(
        private array $textModels,
        private array $imageModels,
    ) {}

    /**
     * Ce qu'on reserve sur le plafond avant de demander une image a ce modele. Un modele sans reserve connue
     * reserve la plus forte de la liste : on ne demande pas une image dont on ignore le prix.
     */
    public function imageReserveMicrousd(string $id): int
    {
        $known = array_map(static fn (array $model): int => (int) ($model['reserve_microusd'] ?? 0), $this->imageModels);

        return (int) ($this->imageModels[$id]['reserve_microusd'] ?? ($known === [] ? 0 : max($known)));
    }

    /**
     * Les identifiants acceptes pour un reglage de modele.
     *
     * @return list<string>
     */
    public function idsFor(string $setting): array
    {
        return array_map(strval(...), array_keys($this->modelsFor($setting)));
    }

    /**
     * Les choix d'un reglage de modele, par identifiant : le nom commercial, l'editeur, et pour un modele de
     * texte son prix indicatif par million de tokens lus puis ecrits.
     *
     * @return array<string, string>
     */
    public function optionsFor(string $setting): array
    {
        $options = [];

        foreach ($this->modelsFor($setting) as $id => $model) {
            $label = $this->name((string) $id);

            if (filled($model['provider'] ?? null)) {
                $label .= ' ('.$model['provider'].')';
            }

            if (isset($model['input'], $model['output'])) {
                $label .= ' · '.$this->dollars((int) $model['input']).' / '.$this->dollars((int) $model['output']);
            }

            $options[(string) $id] = $label;
        }

        return $options;
    }

    /** Le nom commercial d'un modele ; son identifiant quand il n'est pas (ou plus) dans la liste. */
    public function name(?string $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return $this->textModels[$id]['name'] ?? $this->imageModels[$id]['name'] ?? $id;
    }

    /** @return array<string, array<string, mixed>> */
    private function modelsFor(string $setting): array
    {
        return $setting === self::IMAGE_SETTING ? $this->imageModels : $this->textModels;
    }

    /** « 2 $ », « 0,10 $ », « 7,50 $ » a partir de cents. */
    private function dollars(int $cents): string
    {
        return number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', ' ').' $';
    }
}

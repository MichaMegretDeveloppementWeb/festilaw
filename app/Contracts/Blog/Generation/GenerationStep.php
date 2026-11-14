<?php

declare(strict_types=1);

namespace App\Contracts\Blog\Generation;

use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;

/**
 * Une etape d'une generation (choisir un sujet, rediger, traduire...). Le moteur l'execute, enregistre son
 * resultat et sa consommation, puis passe a la suivante. Une etape ne touche pas aux tables de generation :
 * elle recoit ce que les etapes precedentes ont produit et rend ce qu'elle a produit.
 *
 * Elle doit pouvoir etre rejouee : le moteur la relance si elle echoue, ou si le worker a ete coupe pendant
 * qu'elle tournait.
 */
interface GenerationStep
{
    /** Cle stable de l'etape (colonne blog_generation_steps.step), en minuscules. */
    public function key(): string;

    /**
     * Duree au-dela de laquelle l'etape est tenue pour perdue. Sert a la duree du verrou : l'etape doit
     * borner elle-meme ses appels exterieurs en dessous de cette valeur.
     */
    public function timeoutSeconds(): int;

    public function execute(GenerationStepContext $context): GenerationStepResult;
}

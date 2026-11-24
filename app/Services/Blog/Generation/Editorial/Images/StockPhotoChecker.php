<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Images;

use App\Ai\Agents\Blog\PhotoCheckAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationUsage;
use App\Data\Blog\Generation\StockPhotoVerdict;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use Laravel\Ai\Files\Image;

/**
 * Le controle d'une photo de la banque d'images avant qu'elle illustre un article (remarque de Micha du
 * 20/11) : le modele du choix du sujet regarde la photo, dit si elle convient a l'article, et la decrit telle
 * qu'elle est. La recherche de la banque rend des resultats approchants (des outils pour un jouet en bois), et
 * la description voulue par la redaction ne decrit pas la photo trouvee.
 */
final readonly class StockPhotoChecker
{
    /** Cle d'etape du controle, pour le niveau d'effort et le journal des appels. */
    public const STEP = 'photo_check';

    /** Le controle prend le modele du choix du sujet, qui voit les images et coute peu. */
    private const MODEL_SETTING = 'topic';

    public function __construct(
        private BlogAgentCaller $caller,
        private BlogPromptLibrary $prompts,
    ) {}

    /**
     * @param  string  $photoPath  la photo telechargee, regardee telle qu'elle serait rangee
     * @param  array<string, mixed>  $request  l'image voulue par la redaction (description, requete)
     * @param  array<string, mixed>  $article  le sujet de l'article (titre, angle, lecteur) : celui du choix du sujet pour
     *                                         un article genere, le titre et l'extrait pour une demande d'image
     * @param  GenerationUsage  $spent  ce que l'etape des images a deja depense, pour l'arret de securite
     *
     * @throws BlogAiProviderException
     * @throws BlogEditorialException quand aucun modele n'est regle pour le choix du sujet
     */
    public function check(string $photoPath, array $request, array $article, GenerationStepContext $context, GenerationUsage $spent): StockPhotoVerdict
    {
        $model = (string) $context->settings->modelFor(self::MODEL_SETTING);

        if ($model === '') {
            throw BlogEditorialException::noModel(self::STEP, self::MODEL_SETTING);
        }

        $prompt = implode("\n\n", [
            "## Article\n\n".json_encode(array_intersect_key($article, array_flip(['title', 'angle', 'reader'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "## Image the writer wanted\n\n".trim((string) ($request['description'] ?? '')),
        ]);

        $reply = $this->caller->call(
            self::STEP, new PhotoCheckAgent($this->prompts->compose('step-photo-check')), $prompt, $model, 90,
            $context->generationId, $context->budgetLeftAfter($spent), $context->effort, [Image::fromPath($photoPath)],
        );
        $answer = $reply->structured();
        $alt = array_filter([
            'en' => trim((string) ($answer['alt_en'] ?? '')),
            'fr' => trim((string) ($answer['alt_fr'] ?? '')),
            'es' => trim((string) ($answer['alt_es'] ?? '')),
        ], filled(...));

        // Une photo sans description anglaise ne se range pas : son texte alternatif decrirait l'image voulue.
        return new StockPhotoVerdict(($answer['fits'] ?? false) === true && isset($alt['en']), trim((string) ($answer['reason'] ?? '')), $alt, $reply->usage);
    }
}

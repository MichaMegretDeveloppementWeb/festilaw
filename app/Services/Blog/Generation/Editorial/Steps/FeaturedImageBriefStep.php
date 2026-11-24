<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\ImageBriefAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Exceptions\Blog\BlogEditorialException;

/**
 * Premiere etape d'une demande d'image principale (type `image`, demande de Micha du 24/11/2026) : le modele du
 * choix du sujet decrit l'image a trouver pour un article deja ecrit, a partir de son titre et de son extrait.
 * Sa sortie tient lieu, pour l'etape des images, de ce que la redaction et le choix du sujet donnent a un article
 * genere : la demande d'image, le sujet que le controle des photos lit, et les descriptions dans les trois
 * langues. Quand l'article a deja une image, sa description est donnee, pour en obtenir une autre.
 */
final class FeaturedImageBriefStep extends EditorialStep
{
    public function key(): string
    {
        return 'image_brief';
    }

    public function timeoutSeconds(): int
    {
        return 300;
    }

    public function execute(GenerationStepContext $context): GenerationStepResult
    {
        $request = is_array($context->payload['image_request'] ?? null) ? $context->payload['image_request'] : [];
        $title = $this->text($request, 'title');
        $excerpt = $this->text($request, 'excerpt');
        $current = $this->text($request, 'current_alt');

        if ($title === '' && $excerpt === '') {
            throw BlogEditorialException::missingInput($this->key(), 'the title or the excerpt of the article');
        }

        $agent = new ImageBriefAgent($this->prompts->compose('festilaw-context', 'step-image-brief'));
        $prompt = implode("\n\n", array_filter([
            $this->block('Article', array_filter(['title' => $title, 'excerpt' => $excerpt], filled(...))),
            $current !== '' ? $this->block('Current main image, to replace by a different one', $current) : null,
        ]));

        $reply = $this->ask($context, $agent, $prompt);
        $answer = $reply->structured();
        $image = [
            'description' => $this->text($answer, 'description'),
            'search_query' => $this->text($answer, 'search_query'),
            'generation_prompt' => $this->text($answer, 'generation_prompt'),
        ];

        if (in_array('', $image, true)) {
            $this->unusable('The image brief lacks its description, search or prompt.', $reply->usage);
        }

        return GenerationStepResult::continue(
            [
                'featured_image' => $image,
                'alt' => array_filter(['en' => $image['description'], 'fr' => $this->text($answer, 'alt_fr'), 'es' => $this->text($answer, 'alt_es')], filled(...)),
                'article' => array_filter(['title' => $title, 'angle' => $excerpt], filled(...)),
            ],
            $reply->usage,
            $this->report($reply, __('L\'image à trouver a été décrite.')),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Editorial\Steps;

use App\Ai\Agents\Blog\BlogAgent;
use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;

/**
 * Ce que partagent les etapes editoriales : le modele de l'etape lu dans les reglages, l'appel a l'agent par
 * `BlogAgentCaller`, la mise en forme des donnees donnees a lire au modele, et la lecture prudente de sa
 * reponse. Une etape ne touche jamais aux tables de generation : elle rend un `GenerationStepResult`.
 */
abstract class EditorialStep implements GenerationStep
{
    /** Le reglage de modele de chaque etape (la passe de style emploie le modele de la redaction). */
    private const MODEL_SETTINGS = [
        'topic' => 'topic',
        'research' => 'research',
        'writing' => 'writing',
        'verification' => 'verification',
        'style' => 'writing',
        'translation_en' => 'translation',
        'translation_fr' => 'translation',
        'translation_es' => 'translation',
    ];

    public function __construct(
        protected readonly BlogAgentCaller $caller,
        protected readonly BlogPromptLibrary $prompts,
    ) {}

    /** Delai d'un tour de l'agent (une requete au fournisseur). */
    protected function turnTimeoutSeconds(): int
    {
        return 180;
    }

    protected function model(GenerationStepContext $context): string
    {
        $setting = self::MODEL_SETTINGS[$this->key()] ?? $this->key();
        $model = $context->settings->modelFor($setting);

        if (blank($model)) {
            throw BlogEditorialException::noModel($this->key(), $setting);
        }

        return (string) $model;
    }

    /**
     * @param  GenerationUsage|null  $spent  ce que l'etape a deja depense avant cet appel : garde si l'appel echoue
     */
    protected function ask(GenerationStepContext $context, BlogAgent $agent, string $prompt, ?GenerationUsage $spent = null): BlogAgentReply
    {
        try {
            return $this->caller->call($this->key(), $agent, $prompt, $this->model($context), $this->turnTimeoutSeconds());
        } catch (BlogAiProviderException $e) {
            throw $spent === null ? $e : $e->withEarlierUsage($spent);
        }
    }

    /**
     * La reponse du modele est inutilisable alors que ses appels ont ete factures : l'etape echoue (le moteur la
     * reessaie) en gardant ce cout au journal.
     */
    protected function unusable(string $reason, GenerationUsage $spent): never
    {
        throw BlogAiProviderException::callFailed($this->key(), BlogEditorialException::unusableAnswer($this->key(), $reason), $spent);
    }

    /** Une partie du message au modele : un titre, puis des donnees en JSON lisible. */
    protected function block(string $title, mixed $data): string
    {
        $body = is_string($data) ? $data : (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "## {$title}\n\n{$body}";
    }

    /** @param  array<string, mixed>  $data */
    protected function text(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    protected function items(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? array_values($data[$key]) : [];
    }

    /** La phrase du compte rendu de l'agent, ou celle donnee a defaut. */
    protected function report(BlogAgentReply $reply, string $fallback): string
    {
        $report = $this->text($reply->structured(), 'report_fr');

        return $report !== '' ? $report : $fallback;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Blog\Generation\Bench;

use App\Data\Blog\Generation\Bench\BlogBenchTopic;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Exceptions\Blog\BlogBenchException;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\Bench\BlogBenchStepRunner;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;
use App\Services\Blog\Generation\Editorial\Steps\VerificationStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Rejoue la seule verification d'un article deja ecrit par le banc, avec un autre modele, sur le texte que la
 * redaction avait rendu et le dossier de faits du sujet (banc de verification, SC7, decision de Micha du 18/11).
 * Le banc a releve des erreurs de droit que la verification avait laissees passer : rejouee sur les memes textes,
 * elle dit ce que chaque modele attrape. Le resultat est ecrit une fois par article et par modele.
 *
 * Chaque verification a son propre plafond (`blog.ai.bench.verification_cap_cents`) : avant chaque appel, le
 * pipeline reserve son pire cout (la reponse la plus longue permise, fois trois), qui depasse pour certains
 * modeles l'arret de securite d'une seule etape. La depense du banc est annoncee avant par `--dry`.
 */
final readonly class RunBlogBenchVerificationAction
{
    public function __construct(
        private BlogBenchStepRunner $runner,
        private BlogBenchFileStorage $storage,
        private BlogWritingSignalsInspector $words,
        private VerificationStep $step,
        private int $capCents,
    ) {}

    /**
     * @return array<string, mixed> la verification : article verifie, affirmations retirees, cout, duree
     *
     * @throws BlogBenchException
     */
    public function execute(string $run, string $articleKey, string $model, BlogAiSettings $settings): array
    {
        $saved = $this->storage->verification($run, $articleKey, $model);

        if ($saved !== null) {
            return $saved;
        }

        $article = $this->storage->articleByKey($run, $articleKey);
        $prepared = $article === null ? null : $this->storage->topic($run, (string) $article['topic']['key']);

        if ($article === null || $prepared === null) {
            throw BlogBenchException::unknownKeys([$articleKey]);
        }

        $topic = new BlogBenchTopic((string) $article['topic']['key'], BlogTopicKind::from((string) $article['topic']['kind']), (string) $article['topic']['topic']);
        $words = (int) $article['words_asked'];
        $outputs = [...$prepared['outputs'], 'writing' => $article['outputs']['writing']];
        $done = $this->runner->run($this->step, $topic, $settings->withModels(['verification' => $model]), $outputs, $this->capCents * 10_000, $words);

        if (! $done->succeeded()) {
            Log::error('Blog bench verification stopped.', ['article' => $articleKey, 'model' => $model, 'step' => $done->record(), 'exception' => $done->error]);

            throw $done->creditExhausted
                ? BlogBenchException::creditExhausted($done->error)
                : BlogBenchException::stepFailed("{$articleKey} / {$model}", 'verification', $done->error);
        }

        $record = [
            'article' => $articleKey,
            'model' => $model,
            'step' => $done->record(),
            'words' => [
                'before' => $this->words->words((string) ($article['outputs']['writing']['article']['body'] ?? '')),
                'after' => $this->words->words((string) ($done->result->output['article']['body'] ?? '')),
            ],
            'removed_claims' => $done->result->output['removed_claims'] ?? [],
            'checked' => $done->result->output['article'] ?? [],
            'finished_at' => CarbonImmutable::now()->toIso8601String(),
        ];
        $this->storage->saveVerification($run, $articleKey, $model, $record);

        return $record;
    }
}

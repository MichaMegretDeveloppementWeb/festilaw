<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation;

use App\Contracts\Blog\Generation\GenerationStep;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepOutcome;
use App\Exceptions\Blog\BlogAiProviderException;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

/**
 * Execute une etape jusqu'a ce qu'elle reussisse ou epuise ses essais. Chaque essai est enregistre
 * (BlogGenerationStepRecorder), y compris ce qu'il a consomme quand il echoue. Le compteur d'essais vit en
 * base : un essai coupe par la mort du worker compte, et une etape qui a deja consomme tous ses essais
 * n'est pas relancee.
 *
 * La cause d'un echec ne s'affiche nulle part : un essai qui echoue et sera retente est ecrit au journal du
 * serveur (avertissement) ; l'echec final l'est par l'appelant (erreur).
 */
final readonly class BlogGenerationStepRunner
{
    public function __construct(
        private BlogGenerationStepRecorder $recorder,
        private int $maxAttempts,
        private int $retryWaitSeconds,
    ) {}

    /**
     * @param  Closure(int): GenerationStepContext  $context  le contexte de l'etape, pour un numero d'essai
     * @param  Closure(): bool  $holdLock  prolonge le verrou ; faux si un autre worker a repris la generation
     * @param  Closure(): bool  $withinBudget  faux quand l'arret de securite est atteint : aucun essai de plus
     */
    public function run(int $generationId, GenerationStep $step, int $position, Closure $context, Closure $holdLock, Closure $withinBudget): GenerationStepOutcome
    {
        while (true) {
            if (! $holdLock()) {
                return GenerationStepOutcome::lockLost();
            }

            // Verifie avant chaque essai, reprises comprises ; pendant l'essai, chaque appel a l'IA reserve
            // son cout maximal et ne part pas s'il risque de depasser l'arret de securite.
            if (! $withinBudget()) {
                return GenerationStepOutcome::overBudget();
            }

            $row = $this->recorder->begin($generationId, $step->key(), $position);

            if ($row->attempt > $this->maxAttempts) {
                $error = new RuntimeException("Step [{$step->key()}] was interrupted on its last attempt and has none left.");
                $this->recorder->fail($row, $error->getMessage(), null, 0);

                return GenerationStepOutcome::failed($error);
            }

            $startedAt = hrtime(true);

            try {
                $result = $step->execute($context($row->attempt));
            } catch (BlogAiProviderException $e) {
                $this->recorder->fail($row, $e->getMessage(), $e->usage, $this->elapsedMs($startedAt));

                if ($e->overBudget) {
                    return GenerationStepOutcome::overBudget();
                }

                if ($e->creditExhausted || $row->attempt >= $this->maxAttempts) {
                    return GenerationStepOutcome::failed($e, $e->creditExhausted);
                }

                $this->logRetry($generationId, $step, $row->attempt, $e);
                Sleep::for($this->retryWaitSeconds)->seconds();

                continue;
            } catch (Throwable $e) {
                $this->recorder->fail($row, $e->getMessage(), null, $this->elapsedMs($startedAt));

                if ($row->attempt >= $this->maxAttempts) {
                    return GenerationStepOutcome::failed($e);
                }

                $this->logRetry($generationId, $step, $row->attempt, $e);
                Sleep::for($this->retryWaitSeconds)->seconds();

                continue;
            }

            // L'etape a pu durer plus que son verrou, ou la generation a ete arretee pendant l'appel : le
            // resultat n'est pas enregistre (un autre worker fait foi, ou plus rien ne doit s'ecrire), mais son
            // cout, deja facture, l'est.
            if (! $holdLock()) {
                $this->recorder->addAbandonedConsumption($row, $result->usage, $this->elapsedMs($startedAt));

                return GenerationStepOutcome::lockLost();
            }

            $this->recorder->complete($row, $result, $this->elapsedMs($startedAt));

            return GenerationStepOutcome::completed($result);
        }
    }

    private function logRetry(int $generationId, GenerationStep $step, int $attempt, Throwable $e): void
    {
        Log::warning('Blog generation step attempt failed, it will be retried.', [
            'generation_id' => $generationId,
            'step' => $step->key(),
            'attempt' => $attempt,
            'exception' => $e,
        ]);
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}

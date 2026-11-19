<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationPresenter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Back-office : le bouton « Générer un article » et sa fenetre, poses dans l'en-tete des pages de la
 * generation par IA. La fenetre demande le sujet, les consignes, la longueur et les images de CETTE
 * generation, et montre la fourchette de cout qui en resulte ; le lancement mene a la page de la generation,
 * ou le traitement se suit en direct. Le bouton de lancement se desactive quand le haut de la fourchette
 * depasse le plafond par article ; l'Action le refuse de toute facon. Le plafond du mois ne desactive rien :
 * la fenetre avertit seulement quand la depense s'en approche ou l'a depasse.
 *
 * Aucune requete au rendu : les reglages (longueur, images, modeles, plafonds) et la depense du mois ne sont
 * lus qu'a la premiere ouverture de la fenetre (deux requetes).
 */
class GenerationLauncher extends Component
{
    use HandlesAdminErrors;

    /** Sujet impose (facultatif). */
    public string $topicHint = '';

    /** Consignes (facultatif). */
    public string $instructions = '';

    public string $imageMode = '';

    public string $maxImages = '';

    /** Longueur du texte, en mots (saisie). */
    public string $articleWords = '';

    /** Niveau d'effort de cette generation (celui des reglages au depart). */
    public string $effort = '';

    /** @var array<string, string> les modeles des reglages, pour la fourchette de cout */
    #[Locked]
    public array $models = [];

    /** @var list<int> les paliers de longueur proposes */
    #[Locked]
    public array $wordOptions = [];

    #[Locked]
    public int $spentThisMonthMicrousd = 0;

    #[Locked]
    public int $monthlyCapMicrousd = 0;

    /** Les reglages ont deja ete lus. */
    public bool $prepared = false;

    /** Appelee a l'ouverture de la fenetre : pre-remplit la demande d'apres les reglages, une seule fois. */
    public function prepare(BlogAiSettingsService $settings, ArticleLengthRules $length, BlogGenerationBudget $budget): void
    {
        $this->resetErrorBag();

        if ($this->prepared) {
            return;
        }

        $current = $settings->current();
        $this->spentThisMonthMicrousd = $budget->spentThisMonthMicrousd();
        $this->monthlyCapMicrousd = $current->monthlySpendCapMicrousd();

        $this->imageMode = $current->imageMode->value;
        $this->maxImages = (string) $current->maxImages;
        $this->articleWords = (string) $length->clamp($current->articleWords);
        $this->effort = $current->effort->value;
        $this->models = $current->models;
        $this->wordOptions = $length->options();
        $this->prepared = true;
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            'topicHint' => ['nullable', 'string', 'max:300'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'imageMode' => ['required', Rule::enum(BlogAiImageMode::class)],
            'maxImages' => ['required', 'integer', 'min:1', 'max:'.BlogAiSettings::MAX_IMAGES],
            'articleWords' => ['required', Rule::in($this->wordOptions)],
            'effort' => ['required', Rule::enum(BlogEffortLevel::class)],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'topicHint.max' => __('Le sujet ne doit pas dépasser 300 caractères.'),
            'instructions.max' => __('Les consignes ne doivent pas dépasser 2 000 caractères.'),
            'imageMode.required' => __('Choisissez d\'où viennent les images.'),
            'imageMode.enum' => __('Choisissez d\'où viennent les images.'),
            'maxImages.required' => __('Choisissez le nombre d\'images.'),
            'maxImages.integer' => __('Choisissez le nombre d\'images.'),
            'maxImages.min' => __('Choisissez le nombre d\'images.'),
            'maxImages.max' => __('Choisissez le nombre d\'images.'),
            'articleWords.required' => __('Choisissez la longueur du texte.'),
            'articleWords.in' => __('Choisissez une longueur dans la liste.'),
            'effort.required' => __('Choisissez le niveau d\'effort.'),
            'effort.enum' => __('Choisissez le niveau d\'effort.'),
        ];
    }

    public function launch(QueueBlogGenerationAction $queue): void
    {
        $validated = $this->validate();

        try {
            $generationId = $queue->execute(new QueueBlogGenerationData(
                topicHint: $validated['topicHint'] ?? null,
                instructions: $validated['instructions'] ?? null,
                requestedBy: auth()->id(),
                images: new GenerationImageOptions(
                    BlogAiImageMode::from((string) $validated['imageMode']),
                    (int) $validated['maxImages'],
                ),
                articleWords: (int) $validated['articleWords'],
                effort: BlogEffortLevel::from((string) $validated['effort']),
            ));
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->dispatch('ui-toast', title: $e->getUserMessage(), type: 'error');

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin queue blog generation');

            return;
        }

        session()->flash('toast-success', __('Génération lancée.'));

        $this->redirectRoute('admin.blog.generation.show', ['generation' => $generationId]);
    }

    public function render(BlogGenerationPresenter $presenter, BlogGenerationCostForecaster $forecaster, ArticleLengthRules $length, BlogGenerationBudget $budget): View
    {
        $forecast = $this->prepared ? $presenter->forecast($forecaster->forecast(
            $this->models,
            $length->clamp((int) $this->articleWords),
            new GenerationImageOptions(BlogAiImageMode::tryFrom($this->imageMode) ?? BlogAiImageMode::Stock, min(BlogAiSettings::MAX_IMAGES, max(1, (int) $this->maxImages))),
            topicImposed: trim($this->topicHint) !== '',
            effort: BlogEffortLevel::tryFrom($this->effort) ?? BlogEffortLevel::Medium,
        )) : null;

        return view('livewire.admin.blog.generation-launcher', [
            'forecast' => $forecast,
            'monthlyWarning' => $this->prepared ? $presenter->monthlyWarning(
                $budget->monthlyLevel($this->spentThisMonthMicrousd, $this->monthlyCapMicrousd),
                $this->spentThisMonthMicrousd,
                $this->monthlyCapMicrousd,
            ) : null,
            'imageModes' => collect(BlogAiImageMode::cases())->mapWithKeys(fn (BlogAiImageMode $mode): array => [$mode->value => $mode->label()])->all(),
            'lengthOptions' => $presenter->lengthOptions($this->wordOptions),
            'effortLevels' => collect(BlogEffortLevel::cases())->mapWithKeys(fn (BlogEffortLevel $level): array => [$level->value => $level->label()])->all(),
            'imageCounts' => collect(range(1, BlogAiSettings::MAX_IMAGES))->mapWithKeys(fn (int $count): array => [(string) $count => $presenter->imageCountLabel($count)])->all(),
        ]);
    }
}

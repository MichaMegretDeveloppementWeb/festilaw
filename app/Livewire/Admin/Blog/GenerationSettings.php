<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\UpdateBlogAiSettingsAction;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationPresenter;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Back-office : reglages de la generation par IA (modele par etape, longueur et images par defaut, budget,
 * destinataire, generation automatique). Aucune cle d'API ici : elle vit dans le fichier d'environnement, et ne
 * regarde pas la personne qui regle.
 *
 * Un modele se choisit dans une liste fermee (BlogAiModelCatalog), jamais par saisie. La lecture passe par
 * BlogAiSettingsService, l'enregistrement par UpdateBlogAiSettingsAction. « Remettre les modeles recommandes »
 * ne fait que remplir les listes : rien n'est garde avant « Enregistrer les reglages », comme pour tout champ.
 *
 * La fourchette de cout d'un article et l'avertissement du plafond du mois se calculent sur les valeurs a
 * l'ecran, avant meme l'enregistrement, pour qu'on voie l'effet d'un changement de longueur, de modele ou de
 * plafond avant de le garder. Il n'y a plus de plafond par article (decision de Micha du 18/11) : l'estimation
 * annonce le cout, et la generation s'arrete par securite au double de son haut.
 */
#[Layout('layouts.admin')]
class GenerationSettings extends Component
{
    use HandlesAdminErrors;

    public bool $enabled = false;

    public string $periodCount = '';

    public string $periodUnit = '';

    public string $articlesPerRun = '';

    public string $pendingDraftsCap = '';

    /** Plafond de depense du mois, en dollars (saisie). */
    public string $monthlySpendCap = '';

    /** @var array<string, string> identifiant de modele par etape */
    public array $models = [];

    /** Images par defaut : celles des generations automatiques, proposees dans la fenetre de lancement. */
    public string $imageMode = '';

    public string $maxImages = '';

    /** Longueur par defaut du texte, en mots (saisie). */
    public string $articleWords = '';

    /** Niveau d'effort par defaut : celui des generations automatiques, propose dans la fenetre de lancement. */
    public string $effort = '';

    /** @var list<int> les paliers de longueur proposes */
    #[Locked]
    public array $wordOptions = [];

    public string $notificationEmail = '';

    /** Lue une fois a l'ouverture, pour situer le plafond du mois saisi sans relire la base a chaque frappe. */
    #[Locked]
    public int $spentThisMonthMicrousd = 0;

    public function mount(BlogAiSettingsService $settings, ArticleLengthRules $length, BlogGenerationBudget $budget): void
    {
        $current = $settings->current();
        $this->spentThisMonthMicrousd = $budget->spentThisMonthMicrousd();

        $this->wordOptions = $length->options();
        $this->articleWords = (string) $length->clamp($current->articleWords);
        $this->effort = $current->effort->value;

        $this->enabled = $current->enabled;
        $this->periodCount = (string) $current->periodCount;
        $this->periodUnit = $current->periodUnit->value;
        $this->articlesPerRun = (string) $current->articlesPerRun;
        $this->pendingDraftsCap = (string) $current->pendingDraftsCap;
        $this->monthlySpendCap = number_format($current->monthlySpendCapCents / 100, 2, '.', '');
        $this->models = $current->models;
        $this->imageMode = $current->imageMode->value;
        $this->maxImages = (string) $current->maxImages;
        $this->notificationEmail = $current->notificationEmail;
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'periodCount' => ['required', 'integer', 'min:1', 'max:365'],
            'periodUnit' => ['required', Rule::enum(BlogAiPeriodUnit::class)],
            'articlesPerRun' => ['required', 'integer', 'min:1', 'max:10'],
            'pendingDraftsCap' => ['required', 'integer', 'min:1', 'max:50'],
            'monthlySpendCap' => ['required', 'numeric', 'min:0', 'max:10000'],
            'imageMode' => ['required', Rule::enum(BlogAiImageMode::class)],
            'maxImages' => ['required', 'integer', 'min:1', 'max:'.BlogAiSettings::MAX_IMAGES],
            'articleWords' => ['required', Rule::in($this->wordOptions)],
            'effort' => ['required', Rule::enum(BlogEffortLevel::class)],
            'notificationEmail' => ['required', 'email', 'max:180'],
        ];
    }

    /**
     * Chaque etape n'accepte qu'un modele de sa liste : du texte, ou des images pour l'etape des images.
     *
     * @return array<string, array<int, mixed>>
     */
    private function modelRules(BlogAiModelCatalog $catalog): array
    {
        $rules = [];

        foreach (array_keys($this->models) as $step) {
            $rules['models.'.$step] = ['required', 'string', Rule::in($catalog->idsFor((string) $step))];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'periodCount.required' => __('Indiquez la période.'),
            'periodCount.integer' => __('La période est un nombre entier.'),
            'periodCount.min' => __('La période est d\'au moins 1.'),
            'periodCount.max' => __('La période ne peut pas dépasser 365.'),
            'periodUnit.required' => __('Choisissez l\'unité de la période.'),
            'periodUnit.enum' => __('Choisissez l\'unité de la période.'),
            'articlesPerRun.required' => __('Indiquez le nombre d\'articles.'),
            'articlesPerRun.integer' => __('Le nombre d\'articles est un nombre entier.'),
            'articlesPerRun.min' => __('Le nombre d\'articles est d\'au moins 1.'),
            'articlesPerRun.max' => __('Le nombre d\'articles ne peut pas dépasser 10.'),
            'pendingDraftsCap.required' => __('Indiquez le plafond de brouillons en attente.'),
            'pendingDraftsCap.integer' => __('Le plafond de brouillons est un nombre entier.'),
            'pendingDraftsCap.min' => __('Le plafond de brouillons est d\'au moins 1.'),
            'pendingDraftsCap.max' => __('Le plafond de brouillons ne peut pas dépasser 50.'),
            'monthlySpendCap.required' => __('Indiquez le plafond de dépense du mois.'),
            'monthlySpendCap.numeric' => __('Le plafond de dépense est un montant.'),
            'monthlySpendCap.min' => __('Le plafond de dépense ne peut pas être négatif.'),
            'monthlySpendCap.max' => __('Le plafond de dépense ne peut pas dépasser 10 000 $.'),
            'models.*.required' => __('Choisissez un modèle pour cette étape.'),
            'models.*.in' => __('Choisissez un modèle dans la liste.'),
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
            'notificationEmail.required' => __('Indiquez l\'adresse qui reçoit les e-mails.'),
            'notificationEmail.email' => __('Cette adresse e-mail n\'est pas valide.'),
            'notificationEmail.max' => __('L\'adresse e-mail ne doit pas dépasser 180 caractères.'),
        ];
    }

    public function save(UpdateBlogAiSettingsAction $update, BlogAiModelCatalog $catalog): void
    {
        $validated = $this->validate([...$this->rules(), ...$this->modelRules($catalog)]);

        try {
            $update->execute(new BlogAiSettings(
                enabled: (bool) $validated['enabled'],
                periodCount: (int) $validated['periodCount'],
                periodUnit: BlogAiPeriodUnit::from((string) $validated['periodUnit']),
                articlesPerRun: (int) $validated['articlesPerRun'],
                pendingDraftsCap: (int) $validated['pendingDraftsCap'],
                monthlySpendCapCents: (int) round(((float) $validated['monthlySpendCap']) * 100),
                models: $this->models,
                imageMode: BlogAiImageMode::from((string) $validated['imageMode']),
                maxImages: (int) $validated['maxImages'],
                notificationEmail: trim((string) $validated['notificationEmail']),
                articleWords: (int) $validated['articleWords'],
                effort: BlogEffortLevel::from((string) $validated['effort']),
            ));
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update blog AI settings');

            return;
        }

        $this->dispatch('ui-toast', title: __('Réglages enregistrés.'), type: 'success');
    }

    /** Remet les modeles recommandes dans les listes, sans rien enregistrer. */
    public function restoreRecommendedModels(BlogAiSettingsService $settings): void
    {
        $this->models = [...$this->models, ...$settings->recommendedModels()];
        $this->resetValidation('models.*');

        $this->dispatch('ui-toast', title: __('Modèles recommandés remis. Enregistrez les réglages pour les garder.'), type: 'info', life: 6000);
    }

    public function render(
        BlogGenerationPresenter $presenter,
        BlogAiModelCatalog $catalog,
        BlogGenerationCostForecaster $forecaster,
        ArticleLengthRules $length,
        BlogGenerationBudget $budget,
        BlogAiSettingsService $settings,
    ): View {
        $steps = collect($this->models)->keys();
        $recommended = $settings->recommendedModels();
        $images = new GenerationImageOptions(
            BlogAiImageMode::tryFrom($this->imageMode) ?? BlogAiImageMode::Stock,
            min(BlogAiSettings::MAX_IMAGES, max(1, (int) $this->maxImages)),
        );
        $monthlyCap = self::microusd($this->monthlySpendCap);

        return view('livewire.admin.blog.generation-settings', [
            'forecast' => $presenter->forecast($forecaster->forecast(
                $this->models, $length->clamp((int) $this->articleWords), $images,
                effort: BlogEffortLevel::tryFrom($this->effort) ?? BlogEffortLevel::Medium,
            )),
            'monthlyWarning' => $monthlyCap === null ? null : $presenter->monthlyWarning(
                $budget->monthlyLevel($this->spentThisMonthMicrousd, $monthlyCap),
                $this->spentThisMonthMicrousd,
                $monthlyCap,
            ),
            'warningPercent' => $budget->warningPercent(),
            'lengthOptions' => $presenter->lengthOptions($this->wordOptions),
            'modelLabels' => $steps->mapWithKeys(fn (string $step): array => [$step => $presenter->modelSettingLabel($step)])->all(),
            // Un modele enregistre qui n'est plus dans la liste reste visible, pour dire qu'il faut en choisir un autre.
            'modelOptions' => $steps->mapWithKeys(function (string $step) use ($catalog): array {
                $options = $catalog->optionsFor($step);
                $chosen = (string) ($this->models[$step] ?? '');

                return [$step => $chosen === '' || isset($options[$chosen]) ? $options : [$chosen => __(':model (retiré de la liste)', ['model' => $chosen]), ...$options]];
            })->all(),
            'modelsAreRecommended' => array_diff_assoc($recommended, $this->models) === [],
            'periodUnits' => BlogAiPeriodUnit::cases(),
            'imageModes' => collect(BlogAiImageMode::cases())->mapWithKeys(fn (BlogAiImageMode $mode): array => [$mode->value => $mode->label()])->all(),
            'effortLevels' => collect(BlogEffortLevel::cases())->mapWithKeys(fn (BlogEffortLevel $level): array => [$level->value => $level->label()])->all(),
            'imageCounts' => collect(range(1, BlogAiSettings::MAX_IMAGES))->mapWithKeys(fn (int $count): array => [(string) $count => $presenter->imageCountLabel($count)])->all(),
        ])->title(__('Réglages de la génération IA'))->layoutData(['surface' => 'plain']);
    }

    /** Un montant saisi en dollars, ou nul tant que la saisie n'est pas un nombre positif. */
    private static function microusd(string $dollars): ?int
    {
        return is_numeric($dollars) && (float) $dollars >= 0 ? (int) round(((float) $dollars) * 100) * 10_000 : null;
    }
}

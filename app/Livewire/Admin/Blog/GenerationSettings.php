<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\UpdateBlogAiSettingsAction;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
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
 * Back-office : reglages de la generation par IA (automatique, longueur et images par defaut, budget, sujets,
 * modele par etape, destinataire, mention de transparence). Aucune cle d'API ici : elle vit dans le fichier
 * d'environnement, et ne regarde pas la personne qui regle.
 *
 * Un modele se choisit dans une liste fermee (BlogAiModelCatalog), jamais par saisie. Les sujets sont des
 * listes. La lecture passe par BlogAiSettingsService, l'enregistrement par UpdateBlogAiSettingsAction.
 *
 * La fourchette de cout d'un article et l'avertissement du plafond du mois se calculent sur les valeurs a
 * l'ecran, avant meme l'enregistrement, pour qu'on voie l'effet d'un changement de longueur, de modele ou de
 * plafond avant de le garder.
 */
#[Layout('layouts.admin')]
class GenerationSettings extends Component
{
    use HandlesAdminErrors;

    /** Nom des langues, comme sur les autres ecrans du blog. */
    private const LOCALE_NAMES = ['en' => 'anglais', 'fr' => 'français', 'es' => 'espagnol'];

    public bool $enabled = false;

    public string $periodCount = '';

    public string $periodUnit = '';

    public string $articlesPerRun = '';

    public string $pendingDraftsCap = '';

    /** Plafond de depense du mois, en dollars (saisie). */
    public string $monthlySpendCap = '';

    /** Plafond de depense d'une generation, en dollars (saisie). */
    public string $articleSpendCap = '';

    /** @var list<string> sujets et mots-cles a privilegier */
    public array $priorityTopics = [];

    /** @var list<string> sujets a laisser de cote */
    public array $avoidedTopics = [];

    /** @var array<string, string> identifiant de modele par etape */
    public array $models = [];

    /** Images par defaut : celles des generations automatiques, proposees dans la fenetre de lancement. */
    public string $imageMode = '';

    public string $maxImages = '';

    /** Longueur par defaut du texte, en mots (saisie). */
    public string $articleWords = '';

    #[Locked]
    public int $minWords = 0;

    #[Locked]
    public int $maxWords = 0;

    public string $notificationEmail = '';

    /** @var array<string, string> mention de transparence par langue */
    public array $transparencyNotice = [];

    /** Lue une fois a l'ouverture, pour situer le plafond du mois saisi sans relire la base a chaque frappe. */
    #[Locked]
    public int $spentThisMonthMicrousd = 0;

    public function mount(BlogAiSettingsService $settings, ArticleLengthRules $length, BlogGenerationBudget $budget): void
    {
        $current = $settings->current();
        $this->spentThisMonthMicrousd = $budget->spentThisMonthMicrousd();

        $this->minWords = $length->minWords;
        $this->maxWords = $length->maxWords;
        $this->articleWords = (string) $current->articleWords;

        $this->enabled = $current->enabled;
        $this->periodCount = (string) $current->periodCount;
        $this->periodUnit = $current->periodUnit->value;
        $this->articlesPerRun = (string) $current->articlesPerRun;
        $this->pendingDraftsCap = (string) $current->pendingDraftsCap;
        $this->monthlySpendCap = number_format($current->monthlySpendCapCents / 100, 2, '.', '');
        $this->articleSpendCap = number_format($current->articleSpendCapCents / 100, 2, '.', '');
        $this->priorityTopics = $current->priorityTopics;
        $this->avoidedTopics = $current->avoidedTopics;
        $this->models = $current->models;
        $this->imageMode = $current->imageMode->value;
        $this->maxImages = (string) $current->maxImages;
        $this->notificationEmail = $current->notificationEmail;
        $this->transparencyNotice = $current->transparencyNotice;
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
            'articleSpendCap' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'priorityTopics' => ['array', 'max:'.BlogAiSettings::MAX_TOPICS],
            'priorityTopics.*' => ['string', 'max:'.BlogAiSettings::TOPIC_MAX_LENGTH],
            'avoidedTopics' => ['array', 'max:'.BlogAiSettings::MAX_TOPICS],
            'avoidedTopics.*' => ['string', 'max:'.BlogAiSettings::TOPIC_MAX_LENGTH],
            'imageMode' => ['required', Rule::enum(BlogAiImageMode::class)],
            'maxImages' => ['required', 'integer', 'min:1', 'max:'.BlogAiSettings::MAX_IMAGES],
            'articleWords' => ['required', 'integer', 'min:'.$this->minWords, 'max:'.$this->maxWords],
            'notificationEmail' => ['required', 'email', 'max:180'],
            'transparencyNotice.*' => ['nullable', 'string', 'max:500'],
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
            'articlesPerRun.required' => __('Indiquez le nombre d\'articles par lot.'),
            'articlesPerRun.integer' => __('Le nombre d\'articles est un nombre entier.'),
            'articlesPerRun.min' => __('Un lot compte au moins 1 article.'),
            'articlesPerRun.max' => __('Un lot compte 10 articles au plus.'),
            'pendingDraftsCap.required' => __('Indiquez le plafond de brouillons en attente.'),
            'pendingDraftsCap.integer' => __('Le plafond de brouillons est un nombre entier.'),
            'pendingDraftsCap.min' => __('Le plafond de brouillons est d\'au moins 1.'),
            'pendingDraftsCap.max' => __('Le plafond de brouillons ne peut pas dépasser 50.'),
            'monthlySpendCap.required' => __('Indiquez le plafond de dépense du mois.'),
            'monthlySpendCap.numeric' => __('Le plafond de dépense est un montant.'),
            'monthlySpendCap.min' => __('Le plafond de dépense ne peut pas être négatif.'),
            'monthlySpendCap.max' => __('Le plafond de dépense ne peut pas dépasser 10 000 $.'),
            'articleSpendCap.required' => __('Indiquez le plafond de dépense par article.'),
            'articleSpendCap.numeric' => __('Le plafond par article est un montant.'),
            'articleSpendCap.min' => __('Le plafond par article est d\'au moins 0,01 $.'),
            'articleSpendCap.max' => __('Le plafond par article ne peut pas dépasser 100 $.'),
            'priorityTopics.max' => __('Une liste compte :max sujets au plus.', ['max' => BlogAiSettings::MAX_TOPICS]),
            'priorityTopics.*.max' => __('Un sujet ne doit pas dépasser :max caractères.', ['max' => BlogAiSettings::TOPIC_MAX_LENGTH]),
            'avoidedTopics.max' => __('Une liste compte :max sujets au plus.', ['max' => BlogAiSettings::MAX_TOPICS]),
            'avoidedTopics.*.max' => __('Un sujet ne doit pas dépasser :max caractères.', ['max' => BlogAiSettings::TOPIC_MAX_LENGTH]),
            'models.*.required' => __('Choisissez un modèle pour cette étape.'),
            'models.*.in' => __('Choisissez un modèle dans la liste.'),
            'imageMode.required' => __('Choisissez d\'où viennent les images.'),
            'imageMode.enum' => __('Choisissez d\'où viennent les images.'),
            'maxImages.required' => __('Choisissez le nombre d\'images.'),
            'maxImages.integer' => __('Choisissez le nombre d\'images.'),
            'maxImages.min' => __('Choisissez le nombre d\'images.'),
            'maxImages.max' => __('Choisissez le nombre d\'images.'),
            'articleWords.required' => __('Indiquez la longueur du texte.'),
            'articleWords.integer' => __('La longueur est un nombre entier de mots.'),
            'articleWords.min' => __('Le texte compte au moins :min mots.', ['min' => $this->minWords]),
            'articleWords.max' => __('Le texte compte :max mots au plus.', ['max' => number_format($this->maxWords, 0, ',', ' ')]),
            'notificationEmail.required' => __('Indiquez l\'adresse qui reçoit les e-mails.'),
            'notificationEmail.email' => __('Cette adresse e-mail n\'est pas valide.'),
            'notificationEmail.max' => __('L\'adresse e-mail ne doit pas dépasser 180 caractères.'),
            'transparencyNotice.*.max' => __('La mention de transparence ne doit pas dépasser 500 caractères.'),
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
                articleSpendCapCents: (int) round(((float) $validated['articleSpendCap']) * 100),
                priorityTopics: BlogAiSettings::topics($this->priorityTopics),
                avoidedTopics: BlogAiSettings::topics($this->avoidedTopics),
                models: $this->models,
                imageMode: BlogAiImageMode::from((string) $validated['imageMode']),
                maxImages: (int) $validated['maxImages'],
                notificationEmail: trim((string) $validated['notificationEmail']),
                transparencyNotice: array_map(static fn (?string $notice): string => trim((string) $notice), $this->transparencyNotice),
                articleWords: (int) $validated['articleWords'],
            ));
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update blog AI settings');

            return;
        }

        // L'ecran reprend la liste telle qu'elle est enregistree (sans doublon ni vide).
        $this->priorityTopics = BlogAiSettings::topics($this->priorityTopics);
        $this->avoidedTopics = BlogAiSettings::topics($this->avoidedTopics);

        $this->dispatch('ui-toast', title: __('Réglages enregistrés.'), type: 'success');
    }

    public function render(
        BlogGenerationPresenter $presenter,
        BlogAiModelCatalog $catalog,
        BlogGenerationCostForecaster $forecaster,
        ArticleLengthRules $length,
        BlogGenerationBudget $budget,
    ): View {
        $steps = collect($this->models)->keys();
        $images = new GenerationImageOptions(
            BlogAiImageMode::tryFrom($this->imageMode) ?? BlogAiImageMode::Stock,
            min(BlogAiSettings::MAX_IMAGES, max(1, (int) $this->maxImages)),
        );
        $cap = self::microusd($this->articleSpendCap);
        $monthlyCap = self::microusd($this->monthlySpendCap);

        return view('livewire.admin.blog.generation-settings', [
            'forecast' => $presenter->forecast($forecaster->forecast($this->models, $length->clamp((int) $this->articleWords), $images), $cap),
            'monthlyWarning' => $monthlyCap === null ? null : $presenter->monthlyWarning(
                $budget->monthlyLevel($this->spentThisMonthMicrousd, $monthlyCap),
                $this->spentThisMonthMicrousd,
                $monthlyCap,
            ),
            'warningPercent' => $budget->warningPercent(),
            'modelLabels' => $steps->mapWithKeys(fn (string $step): array => [$step => $presenter->modelSettingLabel($step)])->all(),
            // Un modele enregistre qui n'est plus dans la liste reste visible, pour dire qu'il faut en choisir un autre.
            'modelOptions' => $steps->mapWithKeys(function (string $step) use ($catalog): array {
                $options = $catalog->optionsFor($step);
                $chosen = (string) ($this->models[$step] ?? '');

                return [$step => $chosen === '' || isset($options[$chosen]) ? $options : [$chosen => __(':model (retiré de la liste)', ['model' => $chosen]), ...$options]];
            })->all(),
            'periodUnits' => BlogAiPeriodUnit::cases(),
            'imageModes' => collect(BlogAiImageMode::cases())->mapWithKeys(fn (BlogAiImageMode $mode): array => [$mode->value => $mode->label()])->all(),
            'imageCounts' => collect(range(1, BlogAiSettings::MAX_IMAGES))->mapWithKeys(fn (int $count): array => [(string) $count => $presenter->imageCountLabel($count)])->all(),
            'localeNames' => self::LOCALE_NAMES,
        ])->title(__('Réglages de la génération IA'))->layoutData(['surface' => 'plain']);
    }

    /** Un montant saisi en dollars, ou nul tant que la saisie n'est pas un nombre positif. */
    private static function microusd(string $dollars): ?int
    {
        return is_numeric($dollars) && (float) $dollars >= 0 ? (int) round(((float) $dollars) * 100) * 10_000 : null;
    }
}

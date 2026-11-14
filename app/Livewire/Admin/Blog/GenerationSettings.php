<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\UpdateBlogAiSettingsAction;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationPresenter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Back-office : reglages de la generation par IA (automatique, plafonds, sujets, modele par etape, images,
 * destinataire, mention de transparence). Aucune cle d'API ici : elle vit dans le fichier d'environnement.
 * La lecture passe par BlogAiSettingsService, l'enregistrement par UpdateBlogAiSettingsAction.
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

    public string $priorityTopics = '';

    public string $avoidedTopics = '';

    /** @var array<string, string> identifiant de modele par etape */
    public array $models = [];

    public string $imageMode = '';

    public string $maxImages = '';

    public string $notificationEmail = '';

    /** @var array<string, string> mention de transparence par langue */
    public array $transparencyNotice = [];

    public function mount(BlogAiSettingsService $settings): void
    {
        $current = $settings->current();

        $this->enabled = $current->enabled;
        $this->periodCount = (string) $current->periodCount;
        $this->periodUnit = $current->periodUnit->value;
        $this->articlesPerRun = (string) $current->articlesPerRun;
        $this->pendingDraftsCap = (string) $current->pendingDraftsCap;
        $this->monthlySpendCap = number_format($current->monthlySpendCapCents / 100, 2, '.', '');
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
            'priorityTopics' => ['nullable', 'string', 'max:5000'],
            'avoidedTopics' => ['nullable', 'string', 'max:5000'],
            'models.*' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            'imageMode' => ['required', Rule::enum(BlogAiImageMode::class)],
            'maxImages' => ['required', 'integer', 'min:1', 'max:'.BlogAiSettings::MAX_IMAGES],
            'notificationEmail' => ['required', 'email', 'max:180'],
            'transparencyNotice.*' => ['nullable', 'string', 'max:500'],
        ];
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
            'priorityTopics.max' => __('Les sujets prioritaires ne doivent pas dépasser 5 000 caractères.'),
            'avoidedTopics.max' => __('Les sujets à éviter ne doivent pas dépasser 5 000 caractères.'),
            'models.*.required' => __('Indiquez un modèle pour cette étape.'),
            'models.*.max' => __('L\'identifiant d\'un modèle ne doit pas dépasser 120 caractères.'),
            'models.*.regex' => __('L\'identifiant d\'un modèle s\'écrit comme « fournisseur/modele », sans espace.'),
            'imageMode.required' => __('Choisissez d\'où viennent les images.'),
            'imageMode.enum' => __('Choisissez d\'où viennent les images.'),
            'maxImages.required' => __('Indiquez le nombre d\'images par article.'),
            'maxImages.integer' => __('Le nombre d\'images est un nombre entier.'),
            'maxImages.min' => __('Un article a au moins 1 image.'),
            'maxImages.max' => __('Un article a 3 images au plus.'),
            'notificationEmail.required' => __('Indiquez l\'adresse qui reçoit les e-mails.'),
            'notificationEmail.email' => __('Cette adresse e-mail n\'est pas valide.'),
            'notificationEmail.max' => __('L\'adresse e-mail ne doit pas dépasser 180 caractères.'),
            'transparencyNotice.*.max' => __('La mention de transparence ne doit pas dépasser 500 caractères.'),
        ];
    }

    public function save(UpdateBlogAiSettingsAction $update): void
    {
        $validated = $this->validate();

        try {
            $update->execute(new BlogAiSettings(
                enabled: (bool) $validated['enabled'],
                periodCount: (int) $validated['periodCount'],
                periodUnit: BlogAiPeriodUnit::from((string) $validated['periodUnit']),
                articlesPerRun: (int) $validated['articlesPerRun'],
                pendingDraftsCap: (int) $validated['pendingDraftsCap'],
                monthlySpendCapCents: (int) round(((float) $validated['monthlySpendCap']) * 100),
                priorityTopics: trim((string) ($validated['priorityTopics'] ?? '')),
                avoidedTopics: trim((string) ($validated['avoidedTopics'] ?? '')),
                models: array_map(trim(...), $this->models),
                imageMode: BlogAiImageMode::from((string) $validated['imageMode']),
                maxImages: (int) $validated['maxImages'],
                notificationEmail: trim((string) $validated['notificationEmail']),
                transparencyNotice: array_map(static fn (?string $notice): string => trim((string) $notice), $this->transparencyNotice),
            ));
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin update blog AI settings');

            return;
        }

        $this->dispatch('ui-toast', title: __('Réglages enregistrés.'), type: 'success');
    }

    public function render(BlogAiSettingsService $settings, BlogGenerationPresenter $presenter): View
    {
        return view('livewire.admin.blog.generation-settings', [
            'knownModels' => $settings->knownModels(),
            'modelLabels' => collect($this->models)->keys()->mapWithKeys(fn (string $step): array => [$step => $presenter->modelSettingLabel($step)])->all(),
            'periodUnits' => BlogAiPeriodUnit::cases(),
            'imageModes' => BlogAiImageMode::cases(),
            'maxImagesChoices' => range(1, BlogAiSettings::MAX_IMAGES),
            'localeNames' => self::LOCALE_NAMES,
        ])->title(__('Réglages de la génération IA'));
    }
}

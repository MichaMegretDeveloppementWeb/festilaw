<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Generation\QueueBlogGenerationAction;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\QueueBlogGenerationData;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\Generation\BlogGenerationPresenter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Back-office : le bouton « Générer un article » et sa fenetre, poses dans l'en-tete des pages de la
 * generation par IA. La fenetre demande le sujet, les consignes et les images de CETTE generation ; le
 * lancement mene a la page de la generation, ou le traitement se suit en direct.
 *
 * Aucune requete au rendu : les images par defaut ne sont lues dans les reglages qu'a la premiere ouverture
 * de la fenetre (une requete).
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

    /** Les images par defaut des reglages ont deja ete lues. */
    public bool $prepared = false;

    /** Appelee a l'ouverture de la fenetre : pre-remplit les images d'apres les reglages, une seule fois. */
    public function prepare(BlogAiSettingsService $settings): void
    {
        $this->resetErrorBag();

        if ($this->prepared) {
            return;
        }

        $current = $settings->current();

        $this->imageMode = $current->imageMode->value;
        $this->maxImages = (string) $current->maxImages;
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

    public function render(BlogGenerationPresenter $presenter): View
    {
        return view('livewire.admin.blog.generation-launcher', [
            'imageModes' => collect(BlogAiImageMode::cases())->mapWithKeys(fn (BlogAiImageMode $mode): array => [$mode->value => $mode->label()])->all(),
            'imageCounts' => collect(range(1, BlogAiSettings::MAX_IMAGES))->mapWithKeys(fn (int $count): array => [(string) $count => $presenter->imageCountLabel($count)])->all(),
        ]);
    }
}

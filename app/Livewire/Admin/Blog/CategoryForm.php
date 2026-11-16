<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Blog;

use App\Actions\Blog\Category\SaveBlogCategoryAction;
use App\Data\Blog\BlogCategoryTranslationData;
use App\Data\Blog\SaveBlogCategoryData;
use App\Exceptions\BaseAppException;
use App\Livewire\Concerns\HandlesAdminErrors;
use App\Services\Admin\Blog\BlogCategoryAdminService;
use App\Services\Admin\Blog\BlogSlugSuggestionService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Throwable;

/**
 * Back-office : creation et modification d'une categorie du blog, dans les trois langues. Le nom est
 * obligatoire dans chaque langue (une categorie a une page publique dans chacune) ; l'adresse suit le nom
 * tant qu'elle n'a pas ete modifiee a la main, comme pour un article. Aucune requete ici.
 */
#[Layout('layouts.admin')]
class CategoryForm extends Component
{
    use HandlesAdminErrors;

    private const LOCALES = ['en' => 'anglais', 'fr' => 'français', 'es' => 'espagnol'];

    #[Locked]
    public ?int $categoryId = null;

    /** @var array<string, array{name: string, slug: string, description: string, metaTitle: string, metaDescription: string}> */
    public array $texts = [];

    /**
     * Par langue : l'adresse a ete saisie a la main (ou enregistree), elle n'est plus proposee depuis le nom.
     *
     * @var array<string, bool>
     */
    public array $slugEdited = [];

    public function mount(BlogCategoryAdminService $categories, int|string|null $category = null): void
    {
        foreach (array_keys(self::LOCALES) as $locale) {
            $this->texts[$locale] = ['name' => '', 'slug' => '', 'description' => '', 'metaTitle' => '', 'metaDescription' => ''];
            $this->slugEdited[$locale] = false;
        }

        if ($category === null) {
            return;
        }

        $state = $categories->formState((int) $category);

        abort_if($state === null, 404);

        $this->categoryId = $state->categoryId;
        $this->texts = $state->texts;

        // Une adresse deja enregistree ne change plus quand on retouche le nom.
        foreach (array_keys(self::LOCALES) as $locale) {
            $this->slugEdited[$locale] = $state->texts[$locale]['slug'] !== '';
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'texts.*.name' => ['required', 'string', 'max:255'],
            'texts.*.slug' => ['nullable', 'string', 'max:100'],
            'texts.*.description' => ['nullable', 'string', 'max:2000'],
            'texts.*.metaTitle' => ['nullable', 'string', 'max:255'],
            'texts.*.metaDescription' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $messages = [];

        foreach (self::LOCALES as $locale => $language) {
            $messages["texts.{$locale}.name.required"] = "Le nom en {$language} est obligatoire.";
            $messages["texts.{$locale}.name.max"] = "Le nom en {$language} ne peut pas dépasser 255 caractères.";
            $messages["texts.{$locale}.slug.max"] = "L'adresse en {$language} ne peut pas dépasser 100 caractères.";
            $messages["texts.{$locale}.description.max"] = "La description en {$language} ne peut pas dépasser 2 000 caractères.";
            $messages["texts.{$locale}.metaTitle.max"] = "Le titre Google en {$language} ne peut pas dépasser 255 caractères.";
            $messages["texts.{$locale}.metaDescription.max"] = "La description Google en {$language} ne peut pas dépasser 255 caractères.";
        }

        return $messages;
    }

    public function save(SaveBlogCategoryAction $saveCategory): void
    {
        $this->validate();

        $translations = [];

        foreach (array_keys(self::LOCALES) as $locale) {
            $translations[$locale] = new BlogCategoryTranslationData(
                name: $this->texts[$locale]['name'],
                slug: $this->texts[$locale]['slug'],
                description: $this->texts[$locale]['description'],
                metaTitle: $this->texts[$locale]['metaTitle'],
                metaDescription: $this->texts[$locale]['metaDescription'],
            );
        }

        try {
            $saveCategory->execute(new SaveBlogCategoryData($this->categoryId, $translations));
        } catch (BaseAppException $e) {
            Log::error($e->getMessage(), ['exception' => $e]);
            $this->addError('form', $e->getUserMessage());

            return;
        } catch (Throwable $e) {
            $this->reportAdminError($e, 'Admin save blog category');

            return;
        }

        session()->flash('toast-success', $this->categoryId === null ? __('Catégorie créée.') : __('Catégorie enregistrée.'));
        $this->redirectRoute('admin.blog.categories.index');
    }

    /**
     * Adresse proposee pour une langue, a partir du nom saisi. Rien n'est enregistre, rien n'est rendu.
     */
    #[Renderless]
    public function suggestSlug(string $locale, BlogSlugSuggestionService $slugs): string
    {
        if (! array_key_exists($locale, self::LOCALES)) {
            return '';
        }

        try {
            return $slugs->suggestForCategory($locale, (string) ($this->texts[$locale]['name'] ?? ''), $this->categoryId);
        } catch (Throwable $e) {
            // La proposition est un confort : en cas d'echec, le champ reste vide et l'adresse sera
            // calculee a l'enregistrement.
            Log::warning('Blog category slug suggestion failed.', ['locale' => $locale, 'exception' => $e]);

            return '';
        }
    }

    public function render(BlogSlugSuggestionService $slugs): View
    {
        return view('livewire.admin.blog.category-form', [
            'locales' => self::LOCALES,
            'addressPrefixes' => $slugs->pathPrefixes('blog.category'),
        ])->title(($this->categoryId === null ? __('Nouvelle catégorie') : __('Modifier la catégorie')))->layoutData(['surface' => 'plain']);
    }
}

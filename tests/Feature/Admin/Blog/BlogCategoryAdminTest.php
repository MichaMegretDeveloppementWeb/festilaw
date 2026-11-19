<?php

use App\Actions\Blog\Category\ReorderBlogCategoriesAction;
use App\Exceptions\Blog\BlogCategoryException;
use App\Livewire\Admin\Blog\CategoryForm;
use App\Livewire\Admin\Blog\CategoryList;
use App\Models\BlogCategory;
use App\Models\BlogCategoryTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\BlogContent;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

/** Identifiants des categories dans leur ordre d'affichage. */
function categoryOrder(): array
{
    return BlogCategory::query()->orderBy('position')->orderBy('id')->pluck('id')->all();
}

it('lists the categories in their order with their names and their number of articles', function () {
    $rules = BlogContent::category('rules', position: 1);
    $news = BlogContent::category('news', position: 0);
    BlogContent::published('label', $rules);
    BlogContent::draft('pending', $rules);

    Livewire::test(CategoryList::class)
        ->assertOk()
        ->assertSeeInOrder(['News fr', 'Rules fr'])
        ->assertSee('EN · Rules en')
        ->assertSee('2 articles')
        ->assertSee('Aucun article')
        ->assertViewHas('categories', fn (array $rows): bool => array_map(fn ($row) => $row->id, $rows) === [$news->id, $rules->id]);
});

it('loads the category screen in 2 queries', function () {
    foreach (['a', 'b', 'c', 'd'] as $position => $key) {
        BlogContent::published("post-{$key}", BlogContent::category($key, $position));
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(CategoryList::class)->assertOk();

    expect(count(DB::getQueryLog()))->toBe(2);

    DB::disableQueryLog();
});

it('moves a category to its new place and keeps every position distinct', function () {
    $a = BlogContent::category('a', position: 0);
    $b = BlogContent::category('b', position: 1);
    $c = BlogContent::category('c', position: 2);

    Livewire::test(CategoryList::class)
        ->call('reorder', $c->id, 0)
        ->assertDispatched('ui-toast', type: 'success');

    expect(categoryOrder())->toBe([$c->id, $a->id, $b->id])
        ->and(BlogCategory::query()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2]);

    // Une place au-dela de la fin range la categorie en dernier.
    app(ReorderBlogCategoriesAction::class)->execute($c->id, 99);

    expect(categoryOrder())->toBe([$a->id, $b->id, $c->id]);
});

it('refuses to move a category that does not exist', function () {
    BlogContent::category('a');

    expect(fn () => app(ReorderBlogCategoriesAction::class)->execute(999, 0))->toThrow(BlogCategoryException::class);

    Livewire::test(CategoryList::class)->call('reorder', 999, 0)->assertDispatched('ui-toast', type: 'error');
});

it('creates a category in the three languages, the address taken from the name when left empty', function () {
    Livewire::test(CategoryForm::class)
        ->set('texts.en.name', 'Product safety')
        ->set('texts.fr.name', 'Sécurité des produits')
        ->set('texts.fr.slug', 'securite')
        ->set('texts.fr.description', 'Tout sur la sécurité.')
        ->set('texts.es.name', 'Seguridad de los productos')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.blog.categories.index'));

    $category = BlogCategory::query()->with('translations')->sole();

    expect($category->translationFor('en')->slug)->toBe('product-safety')
        ->and($category->translationFor('fr')->slug)->toBe('securite')
        ->and($category->translationFor('fr')->description)->toBe('Tout sur la sécurité.')
        ->and($category->translationFor('es')->slug)->toBe('seguridad-de-los-productos')
        ->and(session('toast-success'))->toBe('Catégorie créée.');
});

it('requires the name in every language, and says which one is missing', function () {
    Livewire::test(CategoryForm::class)
        ->set('texts.fr.name', 'Sécurité')
        ->call('save')
        ->assertHasErrors(['texts.en.name' => 'required', 'texts.es.name' => 'required'])
        ->assertHasNoErrors('texts.fr.name')
        ->assertSee('Le nom en anglais est obligatoire.');

    expect(BlogCategory::query()->count())->toBe(0);
});

it('opens a category with its texts and saves the changes', function () {
    $rules = BlogContent::category('rules');

    Livewire::test(CategoryForm::class, ['category' => $rules->id])
        ->assertSet('categoryId', $rules->id)
        ->assertSet('texts.es.name', 'Rules es')
        ->assertSet('texts.fr.slug', 'rules-fr')
        ->set('texts.fr.name', 'Règles')
        ->set('texts.fr.metaTitle', 'Les règles · Blog Festilaw')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.blog.categories.index'));

    $translation = BlogCategoryTranslation::query()->where('blog_category_id', $rules->id)->where('locale', 'fr')->sole();

    // Le nom change, l'adresse saisie est gardee.
    expect($translation->name)->toBe('Règles')
        ->and($translation->slug)->toBe('rules-fr')
        ->and($translation->meta_title)->toBe('Les règles · Blog Festilaw');
});

it('shows why a category cannot be saved when its address is already used', function () {
    BlogContent::category('rules');

    Livewire::test(CategoryForm::class)
        ->set('texts.en.name', 'Other')
        ->set('texts.fr.name', 'Autre')
        ->set('texts.fr.slug', 'rules-fr')
        ->set('texts.es.name', 'Otra')
        ->call('save')
        ->assertHasErrors('form')
        ->assertSee('est déjà utilisée par une autre catégorie')
        ->assertNoRedirect();

    expect(BlogCategory::query()->count())->toBe(1);
});

it('deletes an empty category and refuses to delete one that still has articles', function () {
    $rules = BlogContent::category('rules');
    $empty = BlogContent::category('empty', position: 1);
    BlogContent::draft('pending', $rules);

    Livewire::test(CategoryList::class)
        ->call('delete', $rules->id)
        ->assertDispatched('ui-toast', type: 'error')
        ->call('delete', $empty->id)
        ->assertDispatched('ui-toast', type: 'success');

    expect(categoryOrder())->toBe([$rules->id]);
});

it('proposes the address of a category from its name, like an article', function () {
    BlogContent::category('rules');

    Livewire::test(CategoryForm::class)
        ->assertSet('slugEdited', ['en' => false, 'fr' => false, 'es' => false])
        ->set('texts.fr.name', 'Sécurité des jouets')
        ->call('suggestSlug', 'fr')
        ->assertReturned('securite-des-jouets')
        // Une adresse deja prise recoit le suffixe qui la garde unique, comme a l'enregistrement.
        ->set('texts.fr.name', 'Rules fr')
        ->call('suggestSlug', 'fr')
        ->assertReturned('rules-fr-2')
        ->set('texts.fr.name', '   ')
        ->call('suggestSlug', 'fr')
        ->assertReturned('')
        ->call('suggestSlug', 'de')
        ->assertReturned('');
});

it('stops following the name once a category has a saved address', function () {
    $rules = BlogContent::category('rules');

    Livewire::test(CategoryForm::class, ['category' => $rules->id])
        ->assertSet('slugEdited', ['en' => true, 'fr' => true, 'es' => true])
        // Sa propre adresse ne compte pas comme deja prise.
        ->call('suggestSlug', 'fr')
        ->assertReturned('rules-fr');
});

it('shows the public path in front of the address, and explains the two Google fields', function () {
    Livewire::test(CategoryForm::class)
        ->assertViewHas('addressPrefixes', ['en' => '/blog/category/', 'fr' => '/fr/blog/categorie/', 'es' => '/es/blog/categoria/'])
        ->assertSee('/fr/blog/categorie/')
        ->assertSee('Résultat dans Google')
        ->assertSee('Si ces champs sont vides, le nom de la catégorie et la description de la catégorie sont utilisés.')
        ->assertDontSee('Sans rien remplir ici')
        ->assertSee('L\'adresse de la page de la catégorie, dans cette langue.')
        ->assertSee('Reprendre l\'adresse depuis le nom')
        ->assertDontSee('Créée à partir du nom')
        ->assertDontSee('Adresse personnalisée')
        ->assertDontSee('Laissez vide');
});

it('opens a category from its whole row, with no edit button', function () {
    $rules = BlogContent::category('rules');

    Livewire::test(CategoryList::class)
        ->assertSeeHtml("window.location.href = '".route('admin.blog.categories.edit', ['category' => $rules->id])."'")
        ->assertDontSeeHtml('title="Modifier"')
        ->assertSee('Supprimer cette catégorie ?');
});

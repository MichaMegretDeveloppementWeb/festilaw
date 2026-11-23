<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog\Generation;

use App\Repositories\Blog\AdminBlogGenerationJournalRepository;
use App\Services\Blog\Generation\Editorial\EditorialMarkdown;

/**
 * Les rares affirmations gardees sur lesquelles la verification a un doute, situees sous leur intertitre
 * francais (decision de Micha du 19/11 : plus la liste de tout ce qui a ete retire ou corrige, plus de texte
 * anglais a l'ecran). La page de la generation et l'editeur du brouillon les montrent tous deux (decision du
 * 23/11 : le lien de l'e-mail mene droit a l'editeur). Une requete.
 */
final readonly class BlogGenerationAttentionPointsService
{
    public function __construct(private AdminBlogGenerationJournalRepository $generations) {}

    /** @return list<array{where: string|null, statement: string, check: string}> */
    public function of(int $generationId): array
    {
        $found = $this->generations->attentionPoints($generationId);
        $checked = EditorialMarkdown::headings($found['checked_body']);
        $french = EditorialMarkdown::headings($found['french_body'] ?? '');

        return array_map(fn (array $point): array => [
            'where' => $this->place($point['heading'], $checked, $french),
            'statement' => $point['statement_fr'],
            'check' => $point['check_fr'],
        ], $found['points']);
    }

    /**
     * Ou se trouve un point dans le brouillon francais : l'intertitre francais de meme rang que l'intertitre
     * anglais cite par la verification. Nul quand il est introuvable, ou quand les deux textes n'ont pas le meme
     * nombre d'intertitres : un lieu faux egarerait plus qu'aucun lieu.
     *
     * @param  list<string>  $checked  intertitres du texte verifie
     * @param  list<string>  $french  intertitres du texte francais final
     */
    private function place(string $heading, array $checked, array $french): ?string
    {
        $heading = ltrim($heading, "# \t");

        if ($heading === '') {
            return __('Dans l\'introduction');
        }

        $comparable = static fn (string $text): string => mb_strtolower(trim((string) preg_replace('/[\s*_`]+/u', ' ', $text)));
        $rank = array_search($comparable($heading), array_map($comparable, $checked), true);

        if ($rank === false || count($checked) !== count($french)) {
            return null;
        }

        return __('Sous l\'intertitre « :heading »', ['heading' => $french[$rank]]);
    }
}

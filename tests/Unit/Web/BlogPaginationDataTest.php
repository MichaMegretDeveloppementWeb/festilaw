<?php

declare(strict_types=1);

use App\Data\Web\Blog\BlogPaginationData;

/*
 | Pagination en fenetre (audit du 23/11) : la premiere page, la derniere, et deux de part et d'autre de la page
 | courante ; null marque un saut.
 */

it('shows a window around the current page, the first and the last page, and marks the gaps', function (int $current, int $last, array $expected) {
    expect((new BlogPaginationData('/blog', $current, $last))->pages())->toBe($expected);
})->with([
    'few pages, all shown' => [2, 5, [1, 2, 3, 4, 5]],
    'start of a long list' => [1, 21, [1, 2, 3, null, 21]],
    'middle of a long list' => [10, 21, [1, null, 8, 9, 10, 11, 12, null, 21]],
    'end of a long list' => [21, 21, [1, null, 19, 20, 21]],
    'a gap of one page shows the page' => [5, 21, [1, 2, 3, 4, 5, 6, 7, null, 21]],
]);

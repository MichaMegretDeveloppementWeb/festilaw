<?php

use App\Services\Web\Blog\BlogHubPageWindow;

it('shows one more post on the first page, for the featured one', function () {
    $window = new BlogHubPageWindow(perPage: 12);

    expect($window->firstPageSize())->toBe(13)
        ->and([$window->offset(1), $window->limit(1)])->toBe([0, 13])
        ->and([$window->offset(2), $window->limit(2)])->toBe([13, 12])
        ->and([$window->offset(3), $window->limit(3)])->toBe([25, 12]);
});

it('counts the pages from the number of published posts', function (int $total, int $lastPage) {
    expect((new BlogHubPageWindow(perPage: 12))->lastPage($total))->toBe($lastPage);
})->with([
    'no post' => [0, 1],
    'one post' => [1, 1],
    'a full first page' => [13, 1],
    'one post over' => [14, 2],
    'two full pages' => [25, 2],
    'one more' => [26, 3],
    'many' => [300, 25],
]);

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Livewire\Component;

/*
 | Garde-fou : une methode publique d'un composant Livewire ne porte jamais le nom d'une methode de
 | tableau JavaScript (sort, filter, map...).
 |
 | Un composant sans aucune propriete publique a pour etat un tableau vide dans le navigateur. `$wire.sort`
 | y designe alors le tri du tableau, pas la methode du serveur : l'appel ne part jamais, sans la moindre
 | erreur visible, et un test PHP ne le voit pas (il appelle la methode directement). C'est ce qui a
 | laisse le glisser-deposer des categories du blog inoperant jusqu'au 2026-11-13 (wire:sort="sort").
 */

it('never names a Livewire action like a JavaScript array method', function () {
    $arrayMembers = [
        'at', 'concat', 'copyWithin', 'entries', 'every', 'fill', 'filter', 'find', 'findIndex', 'findLast',
        'findLastIndex', 'flat', 'flatMap', 'forEach', 'includes', 'indexOf', 'join', 'keys', 'lastIndexOf',
        'length', 'map', 'pop', 'push', 'reduce', 'reduceRight', 'reverse', 'shift', 'slice', 'some', 'sort',
        'splice', 'toReversed', 'toSorted', 'toSpliced', 'toString', 'unshift', 'values', 'with',
    ];

    $offenders = [];

    foreach (File::allFiles(app_path('Livewire')) as $file) {
        $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
            continue;
        }

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Seules les methodes ecrites dans l'application : celles de Livewire lui-meme ne sont pas des actions.
            if (str_starts_with($method->getDeclaringClass()->getName(), 'App\\') && in_array($method->getName(), $arrayMembers, true)) {
                $offenders[] = $method->getDeclaringClass()->getName().'::'.$method->getName();
            }
        }
    }

    expect(array_values(array_unique($offenders)))->toBe([]);
});

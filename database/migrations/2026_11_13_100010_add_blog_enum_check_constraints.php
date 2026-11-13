<?php

declare(strict_types=1);

use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blog : contraintes CHECK sur les colonnes d'enum (meme motif que
 * 2026_07_16_101846_add_enum_check_constraints). MySQL uniquement : SQLite (tests) ne sait pas
 * ajouter une contrainte apres coup, le cast Eloquent y garantit deja la coherence.
 *
 * ATTENTION : la contrainte fige la liste des valeurs. Ajouter un cas a l'un de ces enums exige une
 * migration de rafraichissement (drop puis add), sinon l'ecriture echoue en production.
 */
return new class extends Migration
{
    /** @var list<array{0:string,1:string,2:class-string}> */
    private array $columns = [
        ['blog_posts', 'status', BlogPostStatus::class],
        ['blog_posts', 'origin', BlogPostOrigin::class],
        ['blog_images', 'credit_source', BlogImageCreditSource::class],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as [$table, $column, $enum]) {
            $values = implode(', ', array_map(
                static fn ($case): string => "'".$case->value."'",
                $enum::cases(),
            ));

            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$table}_{$column}_check` CHECK (`{$column}` IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as [$table, $column]) {
            DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$table}_{$column}_check`");
        }
    }
};

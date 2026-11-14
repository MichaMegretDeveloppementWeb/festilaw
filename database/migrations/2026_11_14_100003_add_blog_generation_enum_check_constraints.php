<?php

declare(strict_types=1);

use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationStepStatus;
use App\Enums\Blog\Generation\BlogGenerationTrigger;
use App\Enums\Blog\Generation\BlogGenerationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Blog, generations : contraintes CHECK sur les colonnes d'enum (meme motif que
 * 2026_11_13_100010_add_blog_enum_check_constraints). MySQL uniquement : SQLite (tests) ne sait pas
 * ajouter une contrainte apres coup, le cast Eloquent y garantit deja la coherence.
 *
 * ATTENTION : la contrainte fige la liste des valeurs. Ajouter un cas a l'un de ces enums exige une
 * migration de rafraichissement (drop puis add), sinon l'ecriture echoue en production.
 */
return new class extends Migration
{
    /** @var list<array{0:string,1:string,2:class-string}> */
    private array $columns = [
        ['blog_generations', 'type', BlogGenerationType::class],
        ['blog_generations', 'trigger', BlogGenerationTrigger::class],
        ['blog_generations', 'status', BlogGenerationStatus::class],
        ['blog_generations', 'outcome_reason', BlogGenerationOutcomeReason::class],
        ['blog_generation_steps', 'status', BlogGenerationStepStatus::class],
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

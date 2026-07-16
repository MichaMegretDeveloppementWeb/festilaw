<?php

use App\Enums\Appointment\AppointmentStatus;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contraintes CHECK sur les colonnes d'enum (doctrine migrations : string + CHECK + cast).
 *
 * Appliquees uniquement sous MySQL (production). SQLite (tests) ne supporte pas
 * ALTER TABLE ... ADD CONSTRAINT ; le cast Eloquent y garantit deja la coherence. Les valeurs sont
 * derivees des enums pour rester synchronisees.
 */
return new class extends Migration
{
    /** @var list<array{0:string,1:string,2:class-string}> */
    private array $columns = [
        ['submissions', 'type', SubmissionType::class],
        ['submissions', 'status', SubmissionStatus::class],
        ['contracts', 'signature_status', SignatureStatus::class],
        ['uploaded_documents', 'type', DocumentType::class],
        ['payments', 'type', PaymentType::class],
        ['payments', 'status', PaymentStatus::class],
        ['appointments', 'status', AppointmentStatus::class],
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

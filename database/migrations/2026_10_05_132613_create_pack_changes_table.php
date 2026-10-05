<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changements de pack apres paiement (SC12) : montee Creator -> Pro (signature d'un mandat Pro puis paiement
 * de la difference au prorata) et retour Pro -> Creator (demande validee par Festilaw, effet au
 * renouvellement). Une ligne par demande, conservee comme historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pack_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('from_pack'); // App\Enums\Submission\SubmissionType
            $table->string('to_pack');
            $table->string('status'); // App\Enums\Submission\PackChangeStatus
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete(); // nouveau mandat
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete(); // paiement de la montee
            $table->unsignedInteger('amount_cents')->nullable();
            $table->unsignedSmallInteger('effective_year')->nullable(); // retour au Creator : annee d'effet
            $table->timestamp('eligibility_confirmed_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pack_changes');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : generations d'articles par IA. Une ligne = une generation demandee (au back-office ou par la tache
 * planifiee), traitee etape par etape par le worker. La ligne est aussi la file d'attente : le worker prend
 * la plus ancienne generation encore a traiter et pose un verrou a duree courte (`locked_until`,
 * `lock_owner`), qui expire seul si le processus meurt.
 *
 * `batch_uuid` reunit les generations d'un meme lancement (une seule pour un lancement manuel, N pour un
 * lot planifie) : un e-mail part quand tout le lot est termine (`batch_notified_at`). Les colonnes de sujet
 * (`topic_*`) sont remplies par l'etape « sujet » du pipeline editorial. Les couts sont en millioniemes de
 * dollar (un appel coute moins d'un centime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_generations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20)->default('article'); // App\Enums\Blog\Generation\BlogGenerationType
            $table->string('trigger', 20); // App\Enums\Blog\Generation\BlogGenerationTrigger
            $table->string('status', 20)->default('queued'); // App\Enums\Blog\Generation\BlogGenerationStatus
            $table->string('outcome_reason', 40)->nullable(); // App\Enums\Blog\Generation\BlogGenerationOutcomeReason
            $table->string('current_step', 40)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('batch_uuid');
            $table->timestamp('batch_notified_at')->nullable();
            $table->string('topic_hint', 300)->nullable(); // sujet impose au lancement
            $table->text('instructions')->nullable(); // consignes donnees au lancement
            $table->string('topic_title', 300)->nullable();
            $table->text('topic_summary')->nullable();
            $table->string('topic_kind', 20)->nullable();
            $table->timestamp('topic_rejected_at')->nullable(); // brouillon genere supprime : sujet a ne plus proposer
            $table->foreignId('blog_post_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->unsignedBigInteger('cost_microusd')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->string('lock_owner', 40)->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'locked_until']);
            $table->index('batch_uuid');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_generations');
    }
};

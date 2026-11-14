<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog : etapes d'une generation. Une ligne par etape, creee quand l'etape commence et enregistree avant de
 * passer a la suivante : c'est ce qui permet de reprendre une generation coupee sans refaire, ni repayer,
 * ce qui est deja fait. `attempt` compte les essais de l'etape ; les jetons, les recherches web et le cout
 * (millioniemes de dollar) se cumulent sur tous ses essais. `cost_is_estimated` : le fournisseur n'a pas
 * renvoye le cout reel, il a ete calcule d'apres la table de tarifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_generation_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_generation_id')->constrained()->cascadeOnDelete();
            $table->string('step', 40);
            $table->unsignedSmallInteger('position');
            $table->string('status', 20)->default('pending'); // App\Enums\Blog\Generation\BlogGenerationStepStatus
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->string('model', 120)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('reasoning_tokens')->default(0);
            $table->unsignedSmallInteger('web_searches')->default(0);
            $table->unsignedBigInteger('cost_microusd')->default(0);
            $table->boolean('cost_is_estimated')->default(false);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('error')->nullable();
            $table->json('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['blog_generation_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_generation_steps');
    }
};

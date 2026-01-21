<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exercices budgétaires
        Schema::create('exercices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 20);
            $table->string('libelle', 100);
            $table->year('annee');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['PREPARATION', 'VOTE', 'ACTIF', 'CLOTURE'])->default('PREPARATION');
            $table->boolean('cloture')->default(false);
            $table->timestamp('date_cloture')->nullable();
            $table->uuid('cloture_par')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('cloture_par')->references('id')->on('users')->nullOnDelete();
            $table->unique(['societe_id', 'code']);
        });

        // Natures de dépense (plan comptable OHADA)
        Schema::create('natures_depense', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 20);
            $table->string('libelle', 200);
            $table->text('description')->nullable();
            $table->enum('type', ['FONCTIONNEMENT', 'INVESTISSEMENT', 'STOCKABLE'])->default('FONCTIONNEMENT');
            $table->string('compte_ohada', 20)->nullable()->comment('Compte SYSCOHADA');
            $table->uuid('parent_id')->nullable();
            $table->integer('niveau')->default(1);
            $table->boolean('imputable')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('natures_depense')->nullOnDelete();
            $table->unique(['societe_id', 'code']);
        });

        // Centres de coût
        Schema::create('centres_cout', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 20);
            $table->string('libelle', 200);
            $table->text('description')->nullable();
            $table->uuid('direction_id')->nullable();
            $table->uuid('responsable_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->integer('niveau')->default(1);
            $table->boolean('imputable')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('direction_id')->references('id')->on('directions')->nullOnDelete();
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('centres_cout')->nullOnDelete();
            $table->unique(['societe_id', 'code']);
        });

        // Projets (optionnel)
        Schema::create('projets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('societe_id');
            $table->string('code', 30);
            $table->string('libelle', 200);
            $table->text('description')->nullable();
            $table->uuid('responsable_id')->nullable();
            $table->uuid('direction_id')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->date('date_fin_reelle')->nullable();
            $table->decimal('budget_initial', 18, 0)->nullable()->comment('XAF');
            $table->decimal('budget_revise', 18, 0)->nullable()->comment('XAF');
            $table->enum('statut', ['INITIALISATION', 'EN_COURS', 'SUSPENDU', 'TERMINE', 'ANNULE'])->default('INITIALISATION');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('societe_id')->references('id')->on('societes')->onDelete('cascade');
            $table->foreign('responsable_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('direction_id')->references('id')->on('directions')->nullOnDelete();
            $table->unique(['societe_id', 'code']);
        });

        // Lignes budgétaires
        Schema::create('lignes_budgetaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exercice_id');
            $table->uuid('nature_depense_id');
            $table->uuid('centre_cout_id');
            $table->uuid('projet_id')->nullable();
            $table->decimal('montant_initial', 18, 0)->default(0)->comment('XAF');
            $table->decimal('montant_revise', 18, 0)->default(0)->comment('XAF');
            $table->decimal('montant_engage', 18, 0)->default(0)->comment('XAF - Calculé');
            $table->decimal('montant_facture', 18, 0)->default(0)->comment('XAF - Calculé');
            $table->decimal('montant_paye', 18, 0)->default(0)->comment('XAF - Calculé');
            $table->decimal('montant_disponible', 18, 0)->default(0)->comment('XAF - Calculé');
            $table->boolean('bloque')->default(false);
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->foreign('exercice_id')->references('id')->on('exercices')->onDelete('cascade');
            $table->foreign('nature_depense_id')->references('id')->on('natures_depense')->onDelete('cascade');
            $table->foreign('centre_cout_id')->references('id')->on('centres_cout')->onDelete('cascade');
            $table->foreign('projet_id')->references('id')->on('projets')->nullOnDelete();
            $table->unique(['exercice_id', 'nature_depense_id', 'centre_cout_id', 'projet_id'], 'ligne_budget_unique');
        });

        // Mouvements budgétaires
        Schema::create('mouvements_budget', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ligne_budgetaire_id');
            $table->enum('type_mouvement', [
                'DOTATION_INITIALE', 'REVISION', 'VIREMENT_ENTRANT', 'VIREMENT_SORTANT',
                'ENGAGEMENT', 'DESENGAGEMENT', 'FACTURATION', 'PAIEMENT'
            ]);
            $table->enum('sens', ['CREDIT', 'DEBIT']);
            $table->decimal('montant', 18, 0)->comment('XAF');
            $table->string('document_type', 50)->nullable();
            $table->uuid('document_id')->nullable();
            $table->string('document_numero', 50)->nullable();
            $table->timestamp('date_mouvement');
            $table->text('commentaire')->nullable();
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('ligne_budgetaire_id')->references('id')->on('lignes_budgetaires')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['document_type', 'document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_budget');
        Schema::dropIfExists('lignes_budgetaires');
        Schema::dropIfExists('projets');
        Schema::dropIfExists('centres_cout');
        Schema::dropIfExists('natures_depense');
        Schema::dropIfExists('exercices');
    }
};

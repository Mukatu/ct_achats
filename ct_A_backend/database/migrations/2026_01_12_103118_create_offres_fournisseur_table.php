<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offres_fournisseur', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ligne_demande_achat_id');
            $table->uuid('fournisseur_id');

            // Critères de comparaison
            $table->decimal('prix_unitaire', 15, 0);
            $table->integer('delai_livraison_jours'); // Délai en jours
            $table->integer('conditions_paiement_jours'); // Conditions paiement en jours
            $table->string('garantie')->nullable(); // Ex: "12 mois", "24 mois"
            $table->integer('garantie_mois')->nullable(); // Garantie en mois pour calcul
            $table->enum('offre_technique', ['CONFORME', 'NON_CONFORME'])->default('CONFORME');

            // Informations complémentaires
            $table->text('commentaire')->nullable();
            $table->string('reference_offre')->nullable(); // Référence du devis fournisseur
            $table->date('date_offre')->nullable();
            $table->date('date_validite')->nullable(); // Date limite de validité

            // Score calculé
            $table->decimal('score', 5, 2)->nullable();
            $table->boolean('est_selectionnee')->default(false);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('ligne_demande_achat_id')->references('id')->on('lignes_demande_achat')->onDelete('cascade');
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->onDelete('cascade');

            // Un fournisseur ne peut avoir qu'une offre par ligne
            $table->unique(['ligne_demande_achat_id', 'fournisseur_id'], 'offre_ligne_fournisseur_unique');
        });

        // Ajouter la clé étrangère pour offre_selectionnee_id dans lignes_demande_achat
        Schema::table('lignes_demande_achat', function (Blueprint $table) {
            $table->foreign('offre_selectionnee_id')->references('id')->on('offres_fournisseur')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lignes_demande_achat', function (Blueprint $table) {
            $table->dropForeign(['offre_selectionnee_id']);
        });

        Schema::dropIfExists('offres_fournisseur');
    }
};

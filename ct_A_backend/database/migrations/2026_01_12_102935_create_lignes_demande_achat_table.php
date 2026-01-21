<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_demande_achat', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('demande_achat_id');
            $table->integer('numero_ligne');
            $table->string('designation');
            $table->text('description')->nullable();
            $table->decimal('quantite', 15, 2);
            $table->uuid('unite_mesure_id')->nullable();
            $table->decimal('prix_unitaire_estime', 15, 0)->nullable();
            $table->decimal('montant_estime', 15, 0)->nullable();

            // Offre sélectionnée
            $table->uuid('offre_selectionnee_id')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('demande_achat_id')->references('id')->on('demandes_achat')->onDelete('cascade');
            $table->foreign('unite_mesure_id')->references('id')->on('unites_mesure')->nullOnDelete();

            $table->unique(['demande_achat_id', 'numero_ligne']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_demande_achat');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute le champ reference_origine pour la détection des doublons lors des imports
     */
    public function up(): void
    {
        // Expressions de Besoins
        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->string('reference_origine', 100)->nullable()->after('numero');
            $table->index('reference_origine', 'idx_eb_ref_origine');
        });

        // Demandes d'Achat
        Schema::table('demandes_achat', function (Blueprint $table) {
            $table->string('reference_origine', 100)->nullable()->after('numero');
            $table->index('reference_origine', 'idx_da_ref_origine');
        });

        // Bons de Commande
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->string('reference_origine', 100)->nullable()->after('numero');
            $table->index('reference_origine', 'idx_bc_ref_origine');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->dropIndex('idx_eb_ref_origine');
            $table->dropColumn('reference_origine');
        });

        Schema::table('demandes_achat', function (Blueprint $table) {
            $table->dropIndex('idx_da_ref_origine');
            $table->dropColumn('reference_origine');
        });

        Schema::table('bons_commande', function (Blueprint $table) {
            $table->dropIndex('idx_bc_ref_origine');
            $table->dropColumn('reference_origine');
        });
    }
};

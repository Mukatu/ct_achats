<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rendre zone_id optionnel car les directions sont centrales et communes à toutes les zones.
     */
    public function up(): void
    {
        // Expression de Besoins
        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->uuid('zone_id')->nullable()->change();
        });

        // Demandes d'Achat
        Schema::table('demandes_achat', function (Blueprint $table) {
            $table->uuid('zone_id')->nullable()->change();
        });

        // Bons de Commande
        Schema::table('bons_commande', function (Blueprint $table) {
            $table->uuid('zone_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting to NOT NULL requires all existing records to have a zone_id
        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->uuid('zone_id')->nullable(false)->change();
        });

        Schema::table('demandes_achat', function (Blueprint $table) {
            $table->uuid('zone_id')->nullable(false)->change();
        });

        Schema::table('bons_commande', function (Blueprint $table) {
            $table->uuid('zone_id')->nullable(false)->change();
        });
    }
};

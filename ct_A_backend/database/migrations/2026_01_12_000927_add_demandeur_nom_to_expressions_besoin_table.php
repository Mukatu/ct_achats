<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expressions_besoin', function (Blueprint $table) {
            // Champ pour saisir manuellement le nom du demandeur si non présent dans le système
            $table->string('demandeur_nom')->nullable()->after('demandeur_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expressions_besoin', function (Blueprint $table) {
            $table->dropColumn('demandeur_nom');
        });
    }
};
